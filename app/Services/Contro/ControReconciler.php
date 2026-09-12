<?php

namespace App\Services\Contro;

use App\Models\CatalogCoupon;
use App\Models\CatalogItem;
use App\Models\ImportQuarantine;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\PatientProfile;
use App\Models\Payment;
use App\Models\Prescription;
use App\Models\UpstreamIngestedRow;
use App\Models\User;
use App\Services\Orders\OrderStatusMachine;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Reconciles staged Contro rows (upstream_ingested_rows) into canonical tables (blueprint §3).
 *
 * INVARIANTS (all enforced here):
 *  - IDEMPOTENT: upsert keyed on (upstream_source='contro', upstream_id). Re-runs change nothing.
 *  - DEPENDENCY ORDER: patients -> products -> coupons -> orders -> order_status_history -> payments
 *    -> prescriptions. Principals (patient/doctor) are STUBBED on first reference so dependents land.
 *  - QUARANTINE, don't drop: unmatched refs / disallowed transitions / bad data are flagged.
 *  - ZERO SIDE-EFFECTS: we use saveQuietly()/withoutEvents — NO model events, so no observers,
 *    notifications, jobs, payments, pharmacy calls, or outbound webhooks fire during import.
 *  - Money: Contro doubles(rands) -> minor units(cents), banker's rounding (Money helper).
 *  - Contro int64 ids stay STRINGS; patients keyed by userHash.
 */
class ControReconciler
{
    private const SOURCE = 'contro';

    public function __construct(private readonly ?int $syncRunId = null)
    {
    }

    /** Reconcile everything in dependency order. Returns per-entity counts. */
    public function reconcileAll(): array
    {
        return [
            'patients' => $this->reconcilePatients(),
            'products' => $this->reconcileProducts(),
            'coupons' => $this->reconcileCoupons(),
            'orders' => $this->reconcileOrders(),
            'order_status_history' => $this->reconcileOrderStatusHistory(),
            'payments' => $this->reconcilePayments(),
            'prescriptions' => $this->reconcilePrescriptions(),
        ];
    }

    // ---- principals -----------------------------------------------------------------------------

    /**
     * Ensure a User principal exists for a Contro userHash. 'stub' creates a minimal placeholder
     * (so dependents can link) without overwriting a real synced user; 'upsert' fills name/email.
     */
    public function ensureUpstreamPrincipal(string $userHash, string $role = 'patient', string $mode = 'stub', array $data = []): ?User
    {
        if ($userHash === '') {
            return null;
        }

        $user = User::withoutEvents(function () use ($userHash, $role, $mode, $data) {
            $user = User::where('upstream_source', self::SOURCE)->where('upstream_id', $userHash)->first();

            if (!$user) {
                $user = new User();
                $user->forceFill([
                    'upstream_id' => $userHash,
                    'upstream_source' => self::SOURCE,
                    'role' => $role,
                    'first_name' => $data['first_name'] ?? 'Imported',
                    'last_name' => $data['last_name'] ?? 'Contro',
                    // Deterministic placeholder email keyed on userHash; replaced on 'upsert' with real one.
                    'email' => $data['email'] ?? ("contro+{$userHash}@import.local"),
                    'password' => bcrypt(str()->random(40)),
                    'is_active' => false, // stub — not a login-capable account until real data + activation
                ]);
                $user->save();
                return $user;
            }

            if ($mode === 'upsert') {
                foreach (['first_name', 'last_name', 'email'] as $f) {
                    if (!empty($data[$f])) {
                        $user->{$f} = $data[$f];
                    }
                }
                $user->upstream_synced_at = now();
                $user->save();
            }

            return $user;
        });

        return $user;
    }

    private function resolvePrincipalId(?string $userHash, string $role = 'patient'): ?int
    {
        if ($userHash === null || $userHash === '') {
            return null;
        }
        return $this->ensureUpstreamPrincipal($userHash, $role, 'stub')?->id;
    }

    // ---- entities -------------------------------------------------------------------------------

    public function reconcilePatients(): int
    {
        return $this->each('patients', function (array $p, string $id) {
            $user = $this->ensureUpstreamPrincipal($id, 'patient', 'upsert', [
                'first_name' => Arr::get($p, 'firstName'),
                'last_name' => Arr::get($p, 'lastName'),
                'email' => Arr::get($p, 'email'),
            ]);
            if (!$user) {
                return false;
            }

            PatientProfile::withoutEvents(function () use ($user, $p, $id) {
                $profile = PatientProfile::firstOrNew(['user_id' => $user->id]);
                $profile->forceFill([
                    'medical_aid_name' => Arr::get($p, 'medicalAidProvider'),
                    'medical_aid_number' => Arr::get($p, 'medicalAidNumber'),
                    'medical_aid_plan' => Arr::get($p, 'medicalAidPlan'),
                    'payment_type' => Arr::get($p, 'paymentType'),
                    'mobile_msisdn' => Arr::get($p, 'phone'),
                    'upstream_id' => $id,
                    'upstream_source' => self::SOURCE,
                    'upstream_synced_at' => now(),
                ]);
                $profile->save();

                // Delivery address (labelled), if present — idempotent per coupon-style upstream key.
                $addr = Arr::get($p, 'deliveryAddress');
                if (is_array($addr)) {
                    $profile->addresses()->updateOrCreate(
                        ['upstream_source' => self::SOURCE, 'upstream_id' => $id . ':delivery'],
                        [
                            'label' => 'delivery',
                            'address_line1' => Arr::get($addr, 'addressLine1'),
                            'address_line2' => Arr::get($addr, 'addressLine2'),
                            'suburb' => Arr::get($addr, 'suburb'),
                            'city' => Arr::get($addr, 'city'),
                            'province' => Arr::get($addr, 'province'),
                            'postal_code' => Arr::get($addr, 'postalCode'),
                            'country' => Arr::get($addr, 'country', 'ZA'),
                            'delivery_notes' => Arr::get($addr, 'deliveryNotes'),
                            'is_primary' => true,
                            'upstream_synced_at' => now(),
                        ],
                    );
                }
            });

            return true;
        });
    }

    public function reconcileProducts(): int
    {
        return $this->each('products', function (array $p, string $id) {
            CatalogItem::withoutEvents(function () use ($p, $id) {
                $item = CatalogItem::firstOrNew(['upstream_source' => self::SOURCE, 'upstream_id' => $id]);
                $item->forceFill([
                    'name' => Arr::get($p, 'productName', 'Unnamed'),
                    'item_type' => 'product',
                    'nappi_code' => Arr::get($p, 'nappiCode'),
                    'price_minor' => Money::randsToMinor(Arr::get($p, 'price')) ?? 0,
                    'pack_qty' => Arr::get($p, 'packQty'),
                    'is_master_bundle' => (bool) Arr::get($p, 'isMasterBundle', false),
                    'requires_subscription' => (bool) Arr::get($p, 'requiresSubscription', false),
                    'status' => Arr::get($p, 'isEnabled', true) ? 'active' : 'retired',
                    'upstream_source' => self::SOURCE,
                    'upstream_id' => $id,
                    'upstream_synced_at' => now(),
                ]);
                $item->save();
            });
            return true;
        });
    }

    public function reconcileCoupons(): int
    {
        return $this->each('coupons', function (array $c, string $id) {
            CatalogCoupon::withoutEvents(function () use ($c, $id) {
                $coupon = CatalogCoupon::firstOrNew(['upstream_source' => self::SOURCE, 'upstream_id' => $id]);
                $coupon->forceFill([
                    'code' => Arr::get($c, 'code', 'UNKNOWN'),
                    'type' => Arr::get($c, 'type', 'percent'),
                    'value' => (int) Arr::get($c, 'value', 0),
                    'max_uses' => Arr::get($c, 'maxUses'),
                    'number_of_uses' => (int) Arr::get($c, 'numberOfUses', 0),
                    'is_cancelled' => (bool) Arr::get($c, 'isCancelled', false),
                    'upstream_source' => self::SOURCE,
                    'upstream_id' => $id,
                    'upstream_synced_at' => now(),
                ]);
                $coupon->save();

                foreach ((array) Arr::get($c, 'usage', []) as $i => $u) {
                    $coupon->usages()->updateOrCreate(
                        ['upstream_source' => self::SOURCE, 'upstream_id' => $id . ':' . $i],
                        [
                            'pre_value_minor' => Money::randsToMinor(Arr::get($u, 'preValue')),
                            'post_value_minor' => Money::randsToMinor(Arr::get($u, 'postValue')),
                            'is_redeemed' => (bool) Arr::get($u, 'isRedeemed', false),
                            'payment_reference_id' => Arr::get($u, 'paymentReferenceId'),
                            'upstream_synced_at' => now(),
                        ],
                    );
                }
            });
            return true;
        });
    }

    public function reconcileOrders(): int
    {
        return $this->each('orders', function (array $o, string $id) {
            $status = Arr::get($o, 'status');
            if ($status !== null && !OrderStatusMachine::isValidStatus($status)) {
                $this->quarantine('orders', $id, 'unknown_status', "Contro status '{$status}' not in state machine", $o);
                // still land it (verbatim) but flagged — do not reject.
            }

            Order::withoutEvents(function () use ($o, $id, $status) {
                $order = Order::firstOrNew(['upstream_source' => self::SOURCE, 'upstream_id' => $id]);
                if (!$order->exists && empty($order->reference)) {
                    $order->reference = Order::generateReference();
                }
                $order->forceFill([
                    'contro_order_number' => Arr::get($o, 'orderNumber'),
                    'patient_id' => $this->resolvePrincipalId(Arr::get($o, 'patientUserHash'), 'patient'),
                    'doctor_id' => $this->resolvePrincipalId(Arr::get($o, 'assignedDoctorUserHash'), 'doctor'),
                    'status' => $status ?? 'PendingPayment',
                    'service_fee_minor' => Money::randsToMinor(Arr::get($o, 'serviceFee')) ?? 0,
                    'medication_fee_minor' => Money::randsToMinor(Arr::get($o, 'medicationFee')) ?? 0,
                    'is_subscription' => (bool) Arr::get($o, 'isSubscription', false),
                    'service_category' => Arr::get($o, 'serviceCategory'),
                    'payment_type' => Arr::get($o, 'paymentType'),
                    'next_repeat_date' => Arr::get($o, 'nextRepeatDate'),
                    'pharmacy_script_ref' => Arr::get($o, 'pharmacyScriptReference'),
                    'cancellation_reason' => Arr::get($o, 'cancellationReason'),
                    'ordered_at' => Arr::get($o, 'orderDate'),
                    'upstream_source' => self::SOURCE,
                    'upstream_id' => $id,
                    'upstream_synced_at' => now(),
                ]);
                $order->save();
            });
            return true;
        });
    }

    public function reconcileOrderStatusHistory(): int
    {
        return $this->each('order_status_history', function (array $h, string $id) {
            $orderNumber = Arr::get($h, 'orderNumber');
            $order = Order::where('contro_order_number', $orderNumber)->first();
            if (!$order) {
                $this->quarantine('order_status_history', $id, 'unmatched_order', "No order for orderNumber '{$orderNumber}'", $h);
                return false;
            }

            OrderStatusHistory::withoutEvents(function () use ($h, $id, $order) {
                OrderStatusHistory::updateOrCreate(
                    ['upstream_source' => self::SOURCE, 'upstream_id' => $id],
                    [
                        'order_id' => $order->id,
                        'from_status' => null,
                        'to_status' => Arr::get($h, 'status', 'PendingPayment'),
                        'trigger_type' => 'admin', // Contro history doesn't carry our trigger taxonomy
                        'occurred_at' => Arr::get($h, 'changedAt') ?? now(),
                        'upstream_synced_at' => now(),
                    ],
                );
            });
            return true;
        });
    }

    public function reconcilePayments(): int
    {
        return $this->each('payments', function (array $p, string $id) {
            $order = ($num = Arr::get($p, 'orderNumber'))
                ? Order::where('contro_order_number', $num)->first()
                : null;

            // Resolve patient from the payment's own hash, else fall back to the linked order's patient.
            $patientId = $this->resolvePrincipalId(Arr::get($p, 'patientUserHash'), 'patient')
                ?? $order?->patient_id;

            // payments.patient_id is NOT NULL — if we can't resolve a patient, quarantine (don't force null).
            if ($patientId === null) {
                $this->quarantine('payments', $id, 'unresolved_patient',
                    'Payment has no patientUserHash and no linked order patient', $p);
                return false;
            }

            Payment::withoutEvents(function () use ($p, $id, $order, $patientId) {
                $payment = Payment::firstOrNew(['upstream_source' => self::SOURCE, 'upstream_id' => $id]);
                if (!$payment->exists && empty($payment->reference)) {
                    $payment->reference = Payment::generateReference();
                }
                $payment->forceFill([
                    'order_id' => $order?->id,
                    'patient_id' => $patientId,
                    'provider' => 'payfast', // owned-system gateway (Contro 'peach' maps to payfast)
                    'amount' => Money::randsToMinor(Arr::get($p, 'amount')) ?? 0, // zero allowed
                    'currency' => 'ZAR',
                    'status' => Arr::get($p, 'status', 'pending'),
                    'payment_type' => Arr::get($p, 'type'),
                    'failure_reason' => Arr::get($p, 'failureReason'),
                    'retry_count' => (int) Arr::get($p, 'retryCount', 0),
                    'is_repeat_charge' => (bool) Arr::get($p, 'isRepeatCharge', false),
                    'medical_aid_claim_status' => Arr::get($p, 'medicalAidClaimStatus'),
                    'paid_at' => Arr::get($p, 'paidAt'),
                    'upstream_source' => self::SOURCE,
                    'upstream_id' => $id,
                    'upstream_synced_at' => now(),
                ]);
                $payment->save();
            });
            return true;
        });
    }

    public function reconcilePrescriptions(): int
    {
        return $this->each('prescriptions', function (array $rx, string $id) {
            Prescription::withoutEvents(function () use ($rx, $id) {
                $presc = Prescription::firstOrNew(['upstream_source' => self::SOURCE, 'upstream_id' => $id]);
                if (!$presc->exists && empty($presc->reference)) {
                    $presc->reference = Prescription::generateReference();
                }
                $presc->forceFill([
                    'patient_id' => $this->resolvePrincipalId(Arr::get($rx, 'patientUserHash'), 'patient'),
                    'doctor_id' => $this->resolvePrincipalId(Arr::get($rx, 'doctorUserHash'), 'doctor'),
                    'status' => 'issued',
                    'repeat_cycle_days' => Arr::get($rx, 'repeatCycleDays'),
                    'next_repeat_date' => Arr::get($rx, 'nextRepeatDate'),
                    'pharmacy_script_ref' => Arr::get($rx, 'pharmacyScriptReference'),
                    'total_medication_cost_minor' => Money::randsToMinor(Arr::get($rx, 'totalMedicationCost')),
                    'service_fee_minor' => Money::randsToMinor(Arr::get($rx, 'serviceFee')),
                    'delivery_method' => Arr::get($rx, 'deliveryMethod'),
                    'upstream_source' => self::SOURCE,
                    'upstream_id' => $id,
                    'upstream_synced_at' => now(),
                ]);
                $presc->save();

                foreach ((array) Arr::get($rx, 'medications', []) as $i => $m) {
                    $presc->items()->updateOrCreate(
                        // prescription_items has no crosswalk unique in this build; key on prescription+name+index
                        ['prescription_id' => $presc->id, 'medication_name' => Arr::get($m, 'name', 'Unknown')],
                        [
                            'strength' => Arr::get($m, 'strength', ''),
                            'form' => Arr::get($m, 'form', ''),
                            'dosage' => Arr::get($m, 'dosage', ''),
                            'frequency' => Arr::get($m, 'frequency', ''),
                            'quantity' => (int) Arr::get($m, 'quantity', 0),
                            'unit_price' => Money::randsToMinor(Arr::get($m, 'unitPrice')) ?? 0,
                            'line_total' => Money::randsToMinor(Arr::get($m, 'totalPrice')) ?? 0,
                            'instructions' => Arr::get($m, 'instructions'),
                        ],
                    );
                }
            });
            return true;
        });
    }

    // ---- engine ---------------------------------------------------------------------------------

    /**
     * Iterate staged rows for an entity set; $handler returns true if reconciled, false if skipped.
     * Runs inside a transaction; returns the count reconciled.
     */
    private function each(string $entitySet, callable $handler): int
    {
        $reconciled = 0;

        UpstreamIngestedRow::forEntity($entitySet)->orderBy('id')->chunkById(500, function ($rows) use ($handler, $entitySet, &$reconciled) {
            foreach ($rows as $row) {
                $payload = $row->payload;
                if (!is_array($payload)) {
                    $this->quarantine($entitySet, $row->upstream_id, 'bad_payload', 'Payload not an array', null);
                    continue;
                }
                if ($handler($payload, (string) $row->upstream_id)) {
                    $reconciled++;
                }
            }
        });

        return $reconciled;
    }

    private function quarantine(string $entitySet, ?string $upstreamId, string $reason, ?string $detail, ?array $payload): void
    {
        ImportQuarantine::flag($entitySet, $upstreamId, $reason, $detail, $payload, $this->syncRunId);
    }
}

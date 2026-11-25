<?php

namespace App\Livewire\Admin;

use App\Models\CatalogCoupon;
use App\Services\Operations\Coupons as CouponService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/** Coupons Operations hand out (Craig's guide 6.2). A patient types the code on the payment page. */
class Coupons extends Component
{
    public string $code = '';
    public string $type = 'percent';
    public string $amount = '';
    public string $expires = '';
    public string $maxUses = '';
    public string $patientEmail = '';
    public ?string $created = null;     // the code just made, shown once on screen

    public function create(CouponService $coupons): void
    {
        $this->resetErrorBag();
        $this->created = null;

        $amount = str_replace([' ', ','], ['', '.'], trim($this->amount));
        if (! is_numeric($amount)) {
            $this->addError('amount', $this->type === 'percent' ? 'Enter the percentage, for example 10.' : 'Enter the amount in rand, for example 100.');

            return;
        }
        if ($this->maxUses !== '' && ! ctype_digit(trim($this->maxUses))) {
            $this->addError('max_uses', 'How many times it can be used must be a whole number.');

            return;
        }

        try {
            $coupon = $coupons->create(
                Auth::user(), $this->code, $this->type, (float) $amount, $this->expires ?: null,
                $this->maxUses === '' ? null : (int) $this->maxUses, $this->patientEmail ?: null,
            );
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $this->addError($field, $messages[0]);
            }

            return;
        }

        $this->created = $coupon->code;
        $this->reset(['code', 'amount', 'expires', 'maxUses', 'patientEmail']);
        $this->type = 'percent';
    }

    public function cancelCoupon(int $id, CouponService $coupons): void
    {
        $coupons->cancel(Auth::user(), CatalogCoupon::where('upstream_source', 'zapmed')->findOrFail($id));
    }

    public function render()
    {
        // Only coupons made here (and the abandoned-order ones). Imported Contro coupons are not listed or changed from this page.
        $rows = CatalogCoupon::where('upstream_source', 'zapmed')->orWhere('metadata->origin', 'abandoned_checkout')->orderByDesc('id')->limit(100)->get();

        return view('livewire.admin.coupons', ['coupons' => $rows])->layout('layouts.app');
    }
}
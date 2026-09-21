# SPAR — Health Coach v1 — Tasklist

**Implements:** `requirements.md` + `design.md`. All code in `packages/spar-core`.
Regression rule: the one pre-existing unrelated Auth failure (dashboard 302) must stay at exactly 1.

Legend: [ ] pending · [~] in progress · [x] done

## Phase 0 — Baseline (safety net)
- [ ] 0.1 Record baseline: run standalone SPAR suite, note pass/fail count BEFORE any change.

## Phase 1 — Data + models
- [ ] 1.1 Migration `create_spar_conversations_table` (§2.1).
- [ ] 1.2 Migration `create_spar_messages_table` (§2.2, `body` TEXT for encryption).
- [ ] 1.3 Migration `create_spar_product_suggestions_table` (§2.3).
- [ ] 1.4 Migration `create_spar_order_items_table` (§2.4).
- [ ] 1.5 Models: `SparConversation`, `SparMessage` (EncryptsSensitiveFields on body),
      `SparProductSuggestion`, `SparOrderItem`. `SparOrder::items()` + `basketTotalCents()`.
- [ ] 1.6 `SparConversation::scopeVisibleToCurrentActor` (mirror SparPatient scope logic).

## Phase 2 — Service
- [ ] 2.1 `SparCoachService`: openConversation, postPatientMessage, postStaffMessage,
      suggestProduct, acceptSuggestion, declineSuggestion, markRead (§4).
- [ ] 2.2 Nudge integration via `MessagingDispatcher` (no-PHI copy) on staff→patient + accept.
- [ ] 2.3 Audit events via `LogsSparActivity`/spar_audit.
- [ ] 2.4 Pending-order resolution (find/create basket order) + `SparOrderItem` attach.

## Phase 3 — Staff surface
- [ ] 3.1 `StaffCoachMessages` Livewire (embedded on PatientDetail), scope-gated mount + patient_access.
- [ ] 3.2 Blade: thread + text send + "Suggest a product" mini-form; product/system rendering.
- [ ] 3.3 Wire into `spar::livewire.patient-detail` view (a Messages section/tab).
- [ ] 3.4 Unread badge on PatientList (staff_unread_count).

## Phase 4 — Patient surface
- [ ] 4.1 `PatientCoachMessages` Livewire (embedded in MyMedsTracker dashboard step), consent-gated.
- [ ] 4.2 Blade: thread + text send + suggestion card (Add to my order / No thanks) + system lines.
- [ ] 4.3 Wire a "Messages" panel into `spar::livewire.my-meds-tracker` (post-consent only).

## Phase 5 — Registration + config
- [ ] 5.1 Register 3 Livewire aliases in SparCoreServiceProvider.
- [ ] 5.2 Add `config('spar.coach')` block + env default.

## Phase 6 — Tests (feature, no browser)
- [ ] 6.1 SparCoachConversationTest — open/reuse single open conversation per (patient,pharmacy).
- [ ] 6.2 SparCoachMessagingTest — patient/staff post, unread accounting, markRead, consent hard-stop.
- [ ] 6.3 SparCoachSuggestionTest — suggest → accept (order item + system line + statuses + ids) → decline.
- [ ] 6.4 SparCoachScopeTest — staff cannot open a conversation outside scope (403/empty).
- [ ] 6.5 Package-purity assertion: grep new files for App\Models\User|Prescription|UserRole = 0.
- [ ] 6.6 Re-run full suite: green + baseline fail unchanged (net +N).

## Phase 7 — Chrome verification (per Captain Zan)
- [ ] 7.1 migrate:fresh --seed on standalone; serve.
- [ ] 7.2 Staff: login staff@sparmeds.test / Testing123! → open a consented demo patient → send text
      + product suggestion.
- [ ] 7.3 Patient: open signed tracker link → Messages → reply → "Add to my order".
- [ ] 7.4 Assert: system line posted, order gained the item, unread badges behave, 0 console errors.

## Phase 8 — Wrap
- [ ] 8.1 Update vault memory with build result + resume point. (No commit/push unless "check out".)

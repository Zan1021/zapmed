# Dietitian / Health Coach - TASKS

Scope: `requirements.md`. Design: `design.md`. Weight loss only. Staging only. Estimates are rough working days for one developer (me), excluding waiting on Craig/Meta; a range because several answers are still open.

## Phase 0 - Decisions (blocks Phase A)
- [ ] 0.1 Captain Zan + Craig: when is R450 charged (after prescription recommended)
- [ ] 0.2 Dr Anke: what a dietitian may see (clinical summary scope) + escalation rules
- [ ] 0.3 Fair-use wording for "unlimited" + working hours + reply-time promise
- [ ] 0.4 Dietitian capacity (members each) + cover plan
- [ ] 0.5 Lapse rule (read-only thread, repeat prescriptions)
- [ ] 0.6 Real details for Amy Burger + Angelique Smith (HPCSA/ADSA no., email, photo, bio)

## Phase A - Coach core (MVP) - about 8-12 days
- [ ] A1 Dietitian identity: `coach_profiles` table; move Amy/Angelique from role Doctor to `health_coach`; keep them on the public team section (1 d)
- [ ] A2 Connect journey to membership: after prescription (or per 0.1) show "Add your Health Coach - R450/month" on the patient dashboard and at consult confirmation; reuse PayFast subscription checkout for plan `weight-loss`; fix plan price/text to match Craig (1.5 d)
- [ ] A3 On subscription active: auto-assign dietitian (lowest load under capacity) via `CoachingService::assign`, create conversation, post welcome message (1 d)
- [ ] A4 `coach_conversations` + `coach_messages` + Livewire patient chat (unread badge, read receipts) (2 d)
- [ ] A5 Dietitian workspace `/coach`: caseload list, thread, canned replies, patient summary (read-only), touchpoint note, "flag to doctor" (3 d)
- [ ] A6 Weight tracking: `weight_logs`, log from member home, trend chart styled like Craig's `weight-trend.html`, starting weight pulled from the assessment (1.5 d)
- [ ] A7 Member home (`/member`): "Hi {name}, here's your plan for today", tiles (Coach, Progress; Recipes/Learn/Community greyed "coming soon" until C/D), PWA manifest (1.5 d)
- [ ] A8 Safety: red-flag keyword banner + doctor notification, out-of-hours auto-reply, audit log of coach reads of patient summary (1 d)
- [ ] A9 Admin: capacity + reassignment in the existing coach console (0.5 d)
- [ ] A10 Tests (feature + Livewire), PayFast sandbox end-to-end on zapdev (1.5 d)

## Phase B - WhatsApp - about 5-8 days (+ Meta lead time)
- [ ] B1 Captain Zan/Craig: dedicated ZapMed coaching WhatsApp number on a verified Meta business account
- [ ] B2 Create + submit templates (welcome, weekly check-in, reminder, out-of-window nudge)
- [ ] B3 Inbound webhook -> match phone -> `coach_messages` (channel whatsapp), signature verification (2 d)
- [ ] B4 Outbound from coach reply (free-form in 24h window, template outside) via `WhatsAppChannel` (1.5 d)
- [ ] B5 Opt-in + STOP handling in `consent_records` with wording (1 d)
- [ ] B6 Scheduled check-in nudges (weekly, missed weigh-in) (1 d)
- [ ] B7 Tests with a fake channel; live smoke test on the real number (1 d)

## Phase C - Content - about 6-10 days (content supply is the real bottleneck)
- [ ] C1 `member_content` model + admin CRUD (recipes, exercise guides, learning articles) with images (2 d)
- [ ] C2 Member screens: Recipes, Exercise guides, Learning hub, search/tags (2 d)
- [ ] C3 Free "weight-loss kickstarter" guide: email capture -> PDF/delivery, lead tracking (1.5 d)
- [ ] C4 Meal plans (dietitian attaches a plan/PDF to a patient; later templates) (1.5 d)
- [ ] C5 Content from Craig/dietitians: first 10 recipes, 5 exercise guides, 5 learning articles (waiting on content)
- [ ] C6 Lock content behind active subscription (0.5 d)

## Phase D - Community + partner benefits - about 3-6 days
- [ ] D1 Decide: moderated WhatsApp community (recommended, ~0.5 d to link and explain) vs in-app forum (expensive)
- [ ] D2 `partner_offers` + member page + code reveal (1.5 d)
- [ ] D3 Community guidelines + moderation rota (non-dev)

## Not now
Video calls with the dietitian, AI meal plans, side-effect logging beyond a simple form, other treatment categories, the R220 Everyday plan.

## First thing when we start
Phase 0 answers. Then A1 -> A2 -> A3 (billing and assignment) because they unlock everything else.

## Email to Craig (23 Nov)
- [ ] Captain Zan sends the email. Draft + all 11 questions: vault `2026-11-23_mem_email-to-craig-dietitian-and-launch-questions.md`. Phase 0 answers 1-6 unblock Phase A; 7 unblocks Phase B; 8-11 feed Phase C/D.


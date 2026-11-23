# Dietitian / Health Coach functionality - SCOPE

Written 23 Nov 2026 by Naz for Captain Zan. Staging only (zapdev.co.za). Source: Craig's final handoff (26 Nov zip, `weight-loss/index.html`, `index.html`, `weight-trend.html`, member-app images `membership.webp` + `coach-phone-new.webp`) and a review of what already exists in our code.

## 1. What Craig's design promises (the product)

The dietitian is sold as the **"Health Coach"**, the second half of the weight-loss offer:

| Step | What the patient is told | Price |
|---|---|---|
| 1 | Health assessment, then a video consultation with a **doctor** | R700 once-off |
| 2 | Doctor prescribes (if safe). Medication delivered free, billed separately | per dose |
| 3 | **"Ongoing health coach support. A registered dietitian walks with you the whole way ... check-ins via WhatsApp about nutrition, side-effect management, motivation and real-life adjustments."** | R450 per month |

Promised membership benefits (home + weight-loss pages): Unlimited Health Coach support* (asterisk = fair-use terms still to be written), "Support, any time. Message us whenever you need help", Movement guides, Tracking tools ("notice your progress, beyond the scale"), Meal plans / recipes, a private community, partner discounts, Learning hub, the free "weight-loss kickstarter" guide, and a repeat-prescription fee included.

**The member app Craig drew** (phone mock-up): Home ("Hi there. Here's your plan for today") with five tiles and a bottom tab bar:
1. Health Coach: "Message your coach"
2. Recipes: "Simple, healthy meals"
3. Exercise guides: "Workouts for your goals"
4. Learning hub: "Guides and resources"
5. Community: "Support and motivation"

People: dietitians **Amy Burger RD(SA)** and **Angelique Smith RD(SA), BSc Dietetics (Stellenbosch)** shown under "Registered dietitians" next to the four partner doctors. The coach also appears as a person on a phone screen ("Meet your Health Coach").

**What the demo does NOT show** (we must decide, Craig has not): how a coach is assigned, what a dietitian sees about the patient, doctor to dietitian hand-over, the dietitian's own screens, response times, what "unlimited" means, what happens when someone stops paying but is still on medication, how the free guide is delivered, who writes the recipes/exercises, how the community works.

## 2. What we already have (reuse, do not rebuild)

| Piece | Where | State |
|---|---|---|
| `health_coach` user role | `App\Enums\UserRole` | exists |
| Coach CRM: assign coach (one active coach per patient), log touchpoints (call/WhatsApp/etc), cross-sell offers | `CoachingService`, `CoachingAssignment/Touchpoint/Offer`, admin `/admin/coach-console` (admins + coaches, coaches see only their patients) | exists, back-office only, no patient side |
| R450/month "Weight Loss Programme" plan + PayFast subscriptions (checkout, ITN, pause/resume, cancel, cycles) | `SubscriptionPlan`, `Subscription`, `SubscriptionLifecycle`, `/subscription/*`, `MySubscription` | exists, **not connected to the new booking journey** |
| WhatsApp sending (Meta Cloud API), dev-mode logging until credentials | `WhatsAppChannel` | wired, **no ZapMed credentials / templates**. SPAR proved Meta works but on its own test number |
| Conversation + message + read-marker pattern, product suggestions | SPAR `SparCoachService`, `PatientCoachMessages`, `StaffCoachInbox` | SPAR-specific tables (pharmacy). Pattern reusable, code not |
| Dietitians as people | `PartnerPractitionersSeeder` (Amy, Angelique stored as role Doctor, speciality "Registered Dietitian") | exists; excluded from consult booking (26 Nov) |
| Consent log with wording | `consent_records.wording` (26 Nov) | exists |

## 3. Gaps (what is actually new)

1. Nothing connects the R700 consult to the R450 membership (no "start coaching" step, no first-month rule).
2. No patient-facing coach chat (in-app or WhatsApp) and no two-way WhatsApp.
3. No dietitian workspace (caseload, unread, patient summary, notes).
4. No progress tracking (weight log + chart, side-effect log).
5. No content library (recipes, exercise guides, learning hub, free guide) and no community.
6. No member home / app shell as drawn.
7. No automatic assignment, workload balancing or hand-over doctor to coach.
8. Fair-use / response-time rules not defined.

## 4. Proposed scope, in phases

**Phase A - Coach core (MVP, what makes "R450/month" real).** Subscribe after the consult, auto-assign a dietitian, in-app 1:1 messaging, dietitian caseload + patient summary, weight tracking with trend chart, member home page.
**Phase B - WhatsApp.** Two-way WhatsApp mirrored into the same thread, template-based check-in nudges, consent + opt-out. (Waits on Meta number/templates.)
**Phase C - Content.** Recipes, exercise guides, learning hub, meal plans, the free "kickstarter" guide, all admin-managed.
**Phase D - Community + partner benefits.** Recommend starting with a moderated external group (WhatsApp community) and a simple partner-offers page, not a built-from-scratch forum.

Out of scope for now: video calls with the dietitian, meal-plan generation by AI, other categories (only weight loss), dietitians prescribing, the R220 "Everyday" plan.

## 5. Decisions needed (Captain Zan / Craig / Dr Anke)

1. **When is the R450 charged?** (a) right after the doctor prescribes, from the patient dashboard; (b) at booking with the R700 (first month upfront); (c) 30 days after consult. I recommend (a): they only pay for coaching once treatment is approved.
2. **What can a dietitian see?** Proposal: weight/height/BMI, medicine list + allergies, doctor's plan summary, flags. Not the full consult notes. Needs Dr Anke's yes (HPCSA/POPIA).
3. **WhatsApp number**: a dedicated ZapMed coaching number (as agreed for SPAR) + approved templates. Who creates the Meta business account?
4. **Fair use** behind "unlimited*" and the reply-time promise (e.g. weekdays 08:00-17:00, reply within X hours).
5. **Capacity**: members per dietitian; who covers leave.
6. **If the membership lapses** but medication continues: does the repeat prescription pause? ("Repeat prescription fee included" in the plan.)
7. **Content**: who writes recipes/exercise/learning? Licensed images? Does Craig supply it?
8. **Community**: moderated WhatsApp group (cheap) or built into the app (expensive)?
9. **Partner benefits**: which partners, how are codes issued?
10. **Naming**: "Health Coach" in the product, `health_coach` role in code, "Registered dietitian" as the credential. Keep dietitians out of the doctor list entirely (own role/profile)?

# SPAR Chronic Medication Platform
## A simpler way to manage repeat medication and keep patients connected to SPAR

**Prepared for:** SPAR Pharmacy Group  
**Prepared by:** ZapMed  
**Date:** 23 September 2026

The SPAR Chronic Medication Platform helps patients keep track of their chronic medication, collect their repeats on time, and know when they need a new prescription. It gives pharmacy teams one place to manage collection requests and follow up with patients, while management can see activity across stores.

It is a standalone, SPAR-branded system. Patients access it through a private link on their phone, with no app to download or password to remember.

## How it works

### 1. Use the pharmacy’s existing records

The platform brings together SPAR’s dispensing records and patient contact details to identify each patient, their medication, their pharmacy, and their next collection date. If contact details are missing, pharmacy staff capture them before an invitation can be sent.

The primary member is the household contact and receives one link to view their own and their dependants’ medication information.

### 2. Invite the patient to join

The patient receives a private link by an available messaging channel. When they open it, they are asked for consent before any personal or medication information is displayed. Ongoing reminders only begin once they agree.

### 3. Show the patient what they need to know

Their mobile page shows:

- Their medication and collection pharmacy.
- When their next collection is due.
- Their household’s medication information and previous collections.
- Any prescriptions that need renewal.

SPAR can also display promotional banners on this page.

### 4. Remind them and manage their collection

The platform sends reminders before medication is due for collection. Pharmacy staff manage collection or delivery requests in a shared queue, moving each request from **Requested → Preparing → Ready → Completed**.

This gives staff a clear view of what needs to be prepared and what has already been handed over.

### 5. Help them renew and stay on track

When prescription repeats are running out, the platform prompts the patient to visit their own doctor for a new prescription and return to SPAR to continue collecting medication.

Overdue collections, outstanding renewals, and patients who have stopped returning are highlighted so staff can follow up.

## What pharmacy teams and management see

**Pharmacy staff** see their store’s patients and order queue. They can add missing contact details, check consent, resend a patient’s link, and view the patient’s medication page to help with queries. They cannot give consent or take actions on the patient’s behalf.

**Store and group managers** see activity within their area of responsibility and manage their pharmacies and staff. **Authorised head office administrators** have an overview across all groups and stores.

Reports show patient enrolment, consent, on-time and missed collections, prescriptions due for renewal, and message delivery. This helps SPAR identify where follow-up is needed and compare activity across pharmacies.

## Keeping information up to date

The proposed live setup uses two weekly reports: the **Sales Extract**, containing dispensing activity, and the **Drug Usage report**, containing patient details. SPAR supplies these through a secure file transfer, and the platform imports them automatically to update patient records and reminder schedules.

Administrators can also upload the reports manually. Repeat imports are designed to avoid duplicate records, and patients collecting at another SPAR store are recognised as the same person.

## Patient communication and privacy

WhatsApp is the intended primary messaging channel, with SMS as a fallback and email also available. **WhatsApp is still being provisioned.** The pilot currently uses in-app notifications, email, and interim SMS.

Patients control their consent and can opt out of further communication. Private links expire, and an additional one-time PIN can be requested. Staff access is limited by role, sensitive information is encrypted, and access and changes are recorded. Imported source files are deleted after processing, and SPAR’s data is kept in a separate database.

## The value to SPAR

The platform is designed to support more consistent repeat collections, reduce missed renewals, and help teams reconnect with patients who have stopped returning. Patients get a convenient way to manage their medication, pharmacies get clearer daily workflows, and management gets visibility across the business.

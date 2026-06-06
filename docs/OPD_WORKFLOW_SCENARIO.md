# Hospital Management System (HMS)
# OPD Patient Workflow Scenario

**Complete Patient Journey: From Registration to Checkout**

---

## Overview

This document walks through a complete OPD (Outpatient Department) patient visit from start to finish. Follow along to understand how different roles work together to serve patients.

---

## The Scenario

**Patient:** Kofi Mensah (New Patient)  
**Complaint:** Persistent headache and fever for 3 days  
**Payment Type:** Cash  
**Date:** Monday, March 31, 2026

---

## Step-by-Step Workflow

### STEP 1: Patient Arrival & Registration
**Role: Receptionist**  
**Time: 8:30 AM**

Kofi Mensah arrives at the hospital for the first time.

#### 1.1 Check if Patient Exists

```
Action: Search for patient
Navigation: Patient Management → Patient List
Search: "Kofi Mensah" or phone number
Result: No patient found → Need to register
```

#### 1.2 Register New Patient

```
Navigation: Patient Management → Add Patient

Fill in the form:
┌─────────────────────────────────────────────┐
│ PATIENT REGISTRATION                        │
├─────────────────────────────────────────────┤
│ First Name:     Kofi                        │
│ Last Name:      Mensah                      │
│ Middle Name:    Kwame                       │
│ Date of Birth:  1985-06-15                  │
│ Gender:         Male                        │
│ Phone:          0244123456                  │
│ Address:        123 Main Street, Accra      │
│ Emergency Contact: Ama Mensah (Wife)        │
│ Emergency Phone:   0244789012               │
│ NHIS Number:    (leave blank - Cash patient)│
└─────────────────────────────────────────────┘

Click: [Save]

Result: Patient ID Generated → PT-2026-0542
```

#### 1.3 Register OPD Visit

```
Navigation: OPD → Registration

Fill in the form:
┌─────────────────────────────────────────────┐
│ OPD REGISTRATION                            │
├─────────────────────────────────────────────┤
│ Patient:        PT-2026-0542 - Kofi Mensah  │
│ Visit Date:     2026-03-31 (auto-filled)    │
│ Department:     General Medicine            │
│ Doctor:         Dr. Akua Boateng            │
│ Visit Type:     New Visit                   │
│ Payment Type:   Cash                        │
│ Complaint:      Headache and fever x 3 days │
└─────────────────────────────────────────────┘

Click: [Register]

Result: 
- IOP ID Generated → IOP-2026-03-0089
- Queue Number → 12
- Status: Waiting
```

#### 1.4 Give Patient Queue Number

```
Receptionist tells patient:
"Mr. Mensah, your queue number is 12. 
Please wait in the waiting area. 
You will be called when the doctor is ready."
```

**✓ Receptionist tasks complete**

---

### STEP 2: Vital Signs Recording
**Role: Nurse**  
**Time: 8:45 AM**

Before seeing the doctor, the nurse takes vital signs.

#### 2.1 Find Patient in Queue

```
Navigation: Nursing → Vitals

Search: "Kofi Mensah" or IOP-2026-03-0089
```

#### 2.2 Record Vital Signs

```
Click: [Add Vitals]

Fill in measurements:
┌─────────────────────────────────────────────┐
│ VITAL SIGNS                                 │
├─────────────────────────────────────────────┤
│ Blood Pressure:    130/85 mmHg              │
│ Pulse:             88 bpm                   │
│ Temperature:       38.2 °C                  │
│ Respiratory Rate:  18 breaths/min           │
│ SpO2:              98%                      │
│ Weight:            72 kg                    │
│ Height:            175 cm                   │
│ BMI:               23.5 (auto-calculated)   │
└─────────────────────────────────────────────┘

Click: [Save]

Result: Vitals recorded successfully
```

**✓ Nurse tasks complete**

---

### STEP 3: Doctor Consultation
**Role: Doctor (Dr. Akua Boateng)**  
**Time: 9:15 AM**

#### 3.1 View Patient Queue

```
Navigation: OPD → Queue

Doctor's Queue:
┌────┬─────────────────┬──────────┬───────────┐
│ #  │ Patient         │ Time     │ Status    │
├────┼─────────────────┼──────────┼───────────┤
│ 10 │ Yaw Asante      │ 8:15 AM  │ Completed │
│ 11 │ Esi Owusu       │ 8:22 AM  │ Completed │
│ 12 │ Kofi Mensah     │ 8:30 AM  │ Waiting   │ ← Current
│ 13 │ Abena Darko     │ 8:45 AM  │ Waiting   │
└────┴─────────────────┴──────────┴───────────┘
```

#### 3.2 Call Patient

```
Click: [Call] next to Kofi Mensah

Result: Status changes to "In Consultation"
Patient is called to consulting room
```

#### 3.3 Open Patient EMR

```
Navigation: EMR → OPD → Select Kofi Mensah

EMR Screen Opens:
┌─────────────────────────────────────────────────────┐
│ PATIENT: Kofi Kwame Mensah (PT-2026-0542)          │
│ Age: 40 years | Gender: Male | Visit: IOP-2026-03-0089 │
├─────────────────────────────────────────────────────┤
│ [Summary] [History] [Vitals] [Diagnosis]           │
│ [Prescription] [Lab Orders] [Imaging] [Notes]      │
└─────────────────────────────────────────────────────┘
```

#### 3.4 Review Vitals

```
Click: [Vitals] tab

Vitals Display:
┌─────────────────────────────────────────────┐
│ Today's Vitals (Recorded: 8:45 AM)          │
├─────────────────────────────────────────────┤
│ BP: 130/85 mmHg    Pulse: 88 bpm            │
│ Temp: 38.2°C ⚠️    RR: 18/min               │
│ SpO2: 98%          Weight: 72 kg            │
└─────────────────────────────────────────────┘

Note: Temperature elevated (fever confirmed)
```

#### 3.5 Take History & Examine Patient

Doctor conducts consultation:
- Chief complaint: Headache and fever for 3 days
- History: Started gradually, worse in evenings
- No cough, no vomiting, no diarrhea
- Physical exam: Mild pharyngeal congestion

#### 3.6 Add Clinical Notes

```
Click: [Notes] tab → [Add Note]

┌─────────────────────────────────────────────┐
│ CLINICAL NOTES                              │
├─────────────────────────────────────────────┤
│ Chief Complaint:                            │
│ Headache and fever for 3 days               │
│                                             │
│ History of Present Illness:                 │
│ 40-year-old male presents with 3-day        │
│ history of headache and fever. Headache is  │
│ frontal, throbbing, worse in evenings.      │
│ Associated with mild body aches. No cough,  │
│ no vomiting, no diarrhea, no rash.          │
│                                             │
│ Physical Examination:                       │
│ Alert, oriented. Temp 38.2°C. Mild          │
│ pharyngeal congestion. Chest clear.         │
│ Abdomen soft, non-tender.                   │
│                                             │
│ Assessment:                                 │
│ Viral upper respiratory infection           │
└─────────────────────────────────────────────┘

Click: [Save]
```

#### 3.7 Add Diagnosis

```
Click: [Diagnosis] tab → [Add Diagnosis]

Search: "Upper respiratory"
Select: J06.9 - Acute upper respiratory infection, unspecified

┌─────────────────────────────────────────────┐
│ ADD DIAGNOSIS                               │
├─────────────────────────────────────────────┤
│ Diagnosis: J06.9 - Acute upper respiratory  │
│            infection, unspecified           │
│ Type:      Primary                          │
│ Remarks:   Viral etiology suspected         │
└─────────────────────────────────────────────┘

Click: [Save]
```

#### 3.8 Order Lab Tests

Doctor wants to rule out malaria.

```
Click: [Lab Orders] tab → [Order Lab Test]

┌─────────────────────────────────────────────┐
│ ORDER LAB TEST                              │
├─────────────────────────────────────────────┤
│ Category:    Haematology                    │
│ Test:        Full Blood Count (FBC)         │
│ Priority:    Normal                         │
│ Notes:       R/O infection                  │
└─────────────────────────────────────────────┘

Click: [Order]

Add another test:
┌─────────────────────────────────────────────┐
│ ORDER LAB TEST                              │
├─────────────────────────────────────────────┤
│ Category:    Microbiology                   │
│ Test:        Malaria Parasite (MP)          │
│ Priority:    Normal                         │
│ Notes:       Fever workup                   │
└─────────────────────────────────────────────┘

Click: [Order]

Result: 2 lab tests ordered
```

#### 3.9 Write Prescription

```
Click: [Prescription] tab → [Add Medication]

Medication 1:
┌─────────────────────────────────────────────┐
│ ADD MEDICATION                              │
├─────────────────────────────────────────────┤
│ Drug:        Paracetamol 500mg tablets      │
│ Dosage:      2 tablets                      │
│ Frequency:   3 times daily                  │
│ Duration:    5 days                         │
│ Quantity:    30                             │
│ Instructions: Take after meals              │
└─────────────────────────────────────────────┘
Click: [Add]

Medication 2:
┌─────────────────────────────────────────────┐
│ ADD MEDICATION                              │
├─────────────────────────────────────────────┤
│ Drug:        Vitamin C 500mg tablets        │
│ Dosage:      1 tablet                       │
│ Frequency:   Once daily                     │
│ Duration:    10 days                        │
│ Quantity:    10                             │
│ Instructions: Take in the morning           │
└─────────────────────────────────────────────┘
Click: [Add]

Click: [Save Prescription]

Result: Prescription saved with 2 medications
```

#### 3.10 Complete Consultation

```
Click: [Complete Consultation]

Confirmation: "Are you sure you want to complete this consultation?"
Click: [Yes]

Result: 
- Consultation marked as complete
- Patient moves to billing queue
- Lab orders sent to laboratory
- Prescription sent to pharmacy
```

**✓ Doctor tasks complete**

---

### STEP 4: Laboratory Tests
**Role: Lab Technician**  
**Time: 9:45 AM**

#### 4.1 View Lab Requests

```
Navigation: Laboratory → Requests

Pending Requests:
┌─────────────────────────────────────────────────────────┐
│ Patient         │ Test              │ Doctor    │ Status │
├─────────────────┼───────────────────┼───────────┼────────┤
│ Kofi Mensah     │ Full Blood Count  │ Dr. Boateng│ Pending│
│ Kofi Mensah     │ Malaria Parasite  │ Dr. Boateng│ Pending│
└─────────────────┴───────────────────┴───────────┴────────┘
```

#### 4.2 Collect Sample

```
Click: [Process] next to Full Blood Count

Sample Collection:
- Collect 3ml blood in EDTA tube
- Label with patient details
- Process sample
```

#### 4.3 Enter FBC Results

```
Click: [Enter Results]

┌─────────────────────────────────────────────────────────┐
│ FULL BLOOD COUNT RESULTS                                │
├─────────────────────────────────────────────────────────┤
│ Parameter      │ Result │ Unit    │ Normal Range │ Flag │
├────────────────┼────────┼─────────┼──────────────┼──────┤
│ WBC            │ 11.2   │ x10³/µL │ 4.0-11.0     │ HIGH │
│ RBC            │ 4.8    │ x10⁶/µL │ 4.5-5.5      │      │
│ Hemoglobin     │ 13.5   │ g/dL    │ 12.0-16.0    │      │
│ Hematocrit     │ 42     │ %       │ 36-48        │      │
│ Platelets      │ 245    │ x10³/µL │ 150-400      │      │
│ Neutrophils    │ 72     │ %       │ 40-70        │ HIGH │
│ Lymphocytes    │ 22     │ %       │ 20-40        │      │
└─────────────────────────────────────────────────────────┘

Comments: Mild leukocytosis with neutrophilia, 
          suggestive of bacterial infection

Click: [Save Results]
```

#### 4.4 Enter Malaria Results

```
Click: [Enter Results] for Malaria Parasite

┌─────────────────────────────────────────────┐
│ MALARIA PARASITE RESULTS                    │
├─────────────────────────────────────────────┤
│ Result:    NEGATIVE                         │
│ Method:    Rapid Diagnostic Test (RDT)      │
│ Comments:  No malaria parasites seen        │
└─────────────────────────────────────────────┘

Click: [Save Results]
```

#### 4.5 Validate and Release Results

```
Click: [Validate] for each test

Result: Results released to doctor's view
```

**✓ Lab Technician tasks complete**

---

### STEP 5: Billing
**Role: Cashier**  
**Time: 10:15 AM**

#### 5.1 Create Invoice

```
Navigation: Billing → POS

Search Patient: "Kofi Mensah"
Select: PT-2026-0542 - Kofi Mensah
```

#### 5.2 Add Billing Items

```
Invoice Items:
┌─────────────────────────────────────────────────────────┐
│ # │ Item                      │ Qty │ Rate    │ Amount │
├───┼───────────────────────────┼─────┼─────────┼────────┤
│ 1 │ Consultation Fee          │ 1   │ 50.00   │ 50.00  │
│ 2 │ Full Blood Count          │ 1   │ 80.00   │ 80.00  │
│ 3 │ Malaria Parasite Test     │ 1   │ 30.00   │ 30.00  │
│ 4 │ Paracetamol 500mg x30     │ 30  │ 0.50    │ 15.00  │
│ 5 │ Vitamin C 500mg x10       │ 10  │ 1.00    │ 10.00  │
├───┴───────────────────────────┴─────┴─────────┼────────┤
│                                    Subtotal:  │ 185.00 │
│                                    Discount:  │   0.00 │
│                                    TOTAL:     │ 185.00 │
└───────────────────────────────────────────────┴────────┘

Click: [Save Invoice]

Result: Invoice #INV-2026-03-0156 created
```

#### 5.3 Collect Payment

```
Navigation: Billing → Payment Collection

Find Invoice: INV-2026-03-0156
Click: [Pay]

┌─────────────────────────────────────────────┐
│ COLLECT PAYMENT                             │
├─────────────────────────────────────────────┤
│ Invoice:     INV-2026-03-0156               │
│ Patient:     Kofi Mensah                    │
│ Total Due:   GHS 185.00                     │
│                                             │
│ Amount:      185.00                         │
│ Method:      Cash                           │
│ Reference:   (not required for cash)        │
│ Notes:       Full payment                   │
└─────────────────────────────────────────────┘

Click: [Record Payment]

Result: 
- Payment recorded
- Receipt #RCP-2026-03-0089 generated
- Invoice status: PAID
```

#### 5.4 Print Receipt

```
Click: [Print Receipt]

┌─────────────────────────────────────────────┐
│           HOSPITAL NAME                     │
│           Address Line 1                    │
│           Tel: 0XX-XXX-XXXX                 │
├─────────────────────────────────────────────┤
│         PAYMENT RECEIPT                     │
│                                             │
│ Receipt #:   RCP-2026-03-0089               │
│ Date:        2026-03-31 10:18 AM            │
│ Invoice #:   INV-2026-03-0156               │
│                                             │
│ Patient:     Kofi Mensah                    │
│ Patient ID:  PT-2026-0542                   │
│                                             │
│ Amount Paid: GHS 185.00                     │
│ Method:      Cash                           │
│                                             │
│ Cashier:     Mary Adjei                     │
├─────────────────────────────────────────────┤
│       Thank you for your payment!           │
└─────────────────────────────────────────────┘
```

**✓ Cashier tasks complete**

---

### STEP 6: Pharmacy Dispensing
**Role: Pharmacist**  
**Time: 10:25 AM**

#### 6.1 View Prescription Worklist

```
Navigation: Pharmacy → Worklist

Pending Prescriptions:
┌──────────────────────────────────────────────────────────────────┐
│ Patient      │ Drug              │ Qty │ Status  │ Payer │ Stock│
├──────────────┼───────────────────┼─────┼─────────┼───────┼──────┤
│ Kofi Mensah  │ Paracetamol 500mg │ 30  │ Pending │ CASH  │ 500  │
│ Kofi Mensah  │ Vitamin C 500mg   │ 10  │ Pending │ CASH  │ 200  │
└──────────────┴───────────────────┴─────┴─────────┴───────┴──────┘
```

#### 6.2 Verify Payment

```
Check: Payment status = PAID ✓
(Cash patients must pay before dispensing)
```

#### 6.3 Dispense Paracetamol

```
Click: [Dispense] next to Paracetamol

┌─────────────────────────────────────────────┐
│ DISPENSE MEDICATION                         │
├─────────────────────────────────────────────┤
│ Drug:        Paracetamol 500mg              │
│ Prescribed:  30 tablets                     │
│ Quantity:    30                             │
│ Batch #:     PCM-2026-001                   │
│ Expiry:      2027-12-31                     │
└─────────────────────────────────────────────┘

Click: [Confirm]

Result: 
- Status changes to "Dispensed"
- Stock reduced by 30
```

#### 6.4 Dispense Vitamin C

```
Click: [Dispense] next to Vitamin C

┌─────────────────────────────────────────────┐
│ DISPENSE MEDICATION                         │
├─────────────────────────────────────────────┤
│ Drug:        Vitamin C 500mg                │
│ Prescribed:  10 tablets                     │
│ Quantity:    10                             │
│ Batch #:     VTC-2026-003                   │
│ Expiry:      2027-06-30                     │
└─────────────────────────────────────────────┘

Click: [Confirm]

Result: 
- Status changes to "Dispensed"
- Stock reduced by 10
```

#### 6.5 Counsel Patient

Pharmacist provides instructions:
```
"Mr. Mensah, here are your medications:

1. Paracetamol 500mg - Take 2 tablets 3 times 
   daily after meals for 5 days. This will help 
   with your fever and headache.

2. Vitamin C 500mg - Take 1 tablet every morning 
   for 10 days. This will boost your immunity.

If symptoms persist after 3 days, please return 
to see the doctor."
```

**✓ Pharmacist tasks complete**

---

### STEP 7: Patient Checkout
**Time: 10:35 AM**

#### Summary of Visit

```
┌─────────────────────────────────────────────────────────┐
│              VISIT SUMMARY                              │
├─────────────────────────────────────────────────────────┤
│ Patient:      Kofi Kwame Mensah (PT-2026-0542)         │
│ Visit ID:     IOP-2026-03-0089                         │
│ Date:         March 31, 2026                           │
│                                                         │
│ DIAGNOSIS:                                              │
│ • J06.9 - Acute upper respiratory infection            │
│                                                         │
│ LAB RESULTS:                                            │
│ • FBC: Mild leukocytosis (WBC 11.2)                    │
│ • Malaria: Negative                                     │
│                                                         │
│ MEDICATIONS DISPENSED:                                  │
│ • Paracetamol 500mg x 30                               │
│ • Vitamin C 500mg x 10                                 │
│                                                         │
│ BILLING:                                                │
│ • Invoice: INV-2026-03-0156                            │
│ • Total: GHS 185.00                                    │
│ • Status: PAID                                          │
│                                                         │
│ FOLLOW-UP:                                              │
│ • Return if symptoms persist after 3 days              │
└─────────────────────────────────────────────────────────┘
```

#### Patient Leaves with:
1. ✓ Payment receipt
2. ✓ Medications with instructions
3. ✓ Lab results (if requested)
4. ✓ Follow-up instructions

---

## Workflow Diagram

```
┌──────────────┐
│   PATIENT    │
│   ARRIVES    │
└──────┬───────┘
       │
       ▼
┌──────────────┐     ┌──────────────┐
│ RECEPTIONIST │────▶│   REGISTER   │
│              │     │   PATIENT    │
└──────┬───────┘     └──────────────┘
       │
       ▼
┌──────────────┐     ┌──────────────┐
│    NURSE     │────▶│ RECORD VITALS│
│              │     │              │
└──────┬───────┘     └──────────────┘
       │
       ▼
┌──────────────┐     ┌──────────────┐
│   DOCTOR     │────▶│  CONSULTATION│
│              │     │  DIAGNOSIS   │
│              │     │  LAB ORDERS  │
│              │     │ PRESCRIPTION │
└──────┬───────┘     └──────────────┘
       │
       ├─────────────────┐
       │                 │
       ▼                 ▼
┌──────────────┐  ┌──────────────┐
│  LABORATORY  │  │   CASHIER    │
│  (if ordered)│  │   BILLING    │
└──────┬───────┘  └──────┬───────┘
       │                 │
       └────────┬────────┘
                │
                ▼
       ┌──────────────┐
       │  PHARMACIST  │
       │  DISPENSING  │
       └──────┬───────┘
              │
              ▼
       ┌──────────────┐
       │   PATIENT    │
       │   CHECKOUT   │
       └──────────────┘
```

---

## Time Summary

| Step | Role | Time | Duration |
|------|------|------|----------|
| 1 | Receptionist | 8:30 AM | 10 min |
| 2 | Nurse | 8:45 AM | 5 min |
| 3 | Doctor | 9:15 AM | 25 min |
| 4 | Lab Technician | 9:45 AM | 25 min |
| 5 | Cashier | 10:15 AM | 8 min |
| 6 | Pharmacist | 10:25 AM | 10 min |
| 7 | Checkout | 10:35 AM | - |

**Total Visit Time: ~2 hours**

---

## Key Points to Remember

### For Receptionists:
- Always search for existing patient before registering new
- Verify patient identity before registration
- Assign correct department and doctor
- Give clear queue instructions

### For Nurses:
- Record vitals accurately
- Flag abnormal values
- Inform doctor of urgent findings

### For Doctors:
- Review vitals before consultation
- Document thoroughly
- Order only necessary tests
- Provide clear prescriptions

### For Lab Technicians:
- Process urgent tests first
- Double-check patient identity
- Validate results before release

### For Cashiers:
- Verify all items before billing
- Collect correct payment
- Issue receipt always
- Balance cash at end of day

### For Pharmacists:
- Verify payment before dispensing (Cash patients)
- Check stock and expiry
- Counsel patient on medication use
- Update stock records

---

## Alternative Scenarios

### Scenario A: NHIS Patient
- Payment type = NHIS at registration
- Cashier creates invoice but no immediate payment
- Pharmacist can dispense (NHIS auto-approved)
- Claims submitted to NHIS later

### Scenario B: Follow-up Visit
- Search existing patient (no new registration)
- Visit type = Follow-up
- Doctor reviews previous records
- May not need new lab tests

### Scenario C: Emergency/Urgent
- Priority registration
- Immediate vitals
- Doctor sees immediately
- Urgent lab processing
- Fast-track billing

### Scenario D: Patient Needs Admission (IPD)
- After consultation, doctor decides admission needed
- IPD admission process initiated
- Ward and bed assigned
- Different billing process

---

*This workflow represents a typical OPD visit. Actual processes may vary based on hospital policies and patient needs.*

**© 2026 Hospital Management System**

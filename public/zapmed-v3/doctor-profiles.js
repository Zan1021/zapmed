const doctorProfiles = [
  {
    "name": "Dr Bontle Ntshole",
    "credentials": "MBA · MBChB · BSc (Hons) · BSc",
    "training": [
      "MBA, University of the Witwatersrand (2020–2022)",
      "MBChB, University of Limpopo, MEDUNSA Campus (2004–2010)",
      "BSc (Hons), University of the Witwatersrand (2003)",
      "BSc, University of the Witwatersrand (2000–2002)"
    ],
    "bio": [
      "Dr Bontle Ntshole is a medical doctor and healthcare strategist with a holistic, patient-centred approach to wellbeing. Her special interests include weight management, cardiometabolic health, women’s health and mental health. She combines evidence-based medicine with compassionate support to help patients find practical, sustainable ways to feel better. Dr Ntshole holds an MBA and BSc (Hons) from the University of the Witwatersrand, an MBChB from the University of Limpopo, MEDUNSA Campus, and a BSc from the University of the Witwatersrand.",
      "Outside medicine, she enjoys Sudoku and brain teasers, and is among the 15% of people worldwide who can fold her tongue into a cloverleaf! Incredible! All tongues aside, Dr Bontle is an incredibly compassionate doctor and upholds professionalism and care for her patients with the highest standards."
    ]
  },
  {
    "name": "Dr Gerhard Coetzer",
    "credentials": "MBChB",
    "training": [],
    "bio": []
  },
  {
    "name": "Dr Frances Earle",
    "credentials": "MBChB · DipHIVMan · DipPEC",
    "training": [
      "MBChB, University of Pretoria (2016–2021)",
      "DipHIVMan, Colleges of Medicine of South Africa",
      "DipPEC, Colleges of Medicine of South Africa"
    ],
    "bio": [
      "Dr Frances Earle is a medical doctor with an MBChB from the University of Pretoria, a DipHIVMan and a DipPEC from the Colleges of Medicine of South Africa. A hands-on clinician, she is committed to providing empathetic, patient-centred care, bringing insight and a strong sense of responsibility to her work."
    ]
  },
  {
    "name": "Dr Anke Van Zyl",
    "credentials": "MBBS · Diploma in Obstetrics",
    "training": [
      "MBBS, Stellenbosch University (2015–2020)",
      "Diploma in Obstetrics, Colleges of Medicine of South Africa (2023)",
      "FCOG (SA) Part 1 in Medicine, Colleges of Medicine of South Africa (currently undertaking; began 2024)"
    ],
    "bio": [
      "Dr Anke van Zyl is a medical doctor with an MBBS from Stellenbosch University and a Diploma in Obstetrics from the Colleges of Medicine of South Africa. She is currently undertaking FCOG (SA) Part 1 in Medicine and works across private practice and digital healthcare.",
      "Dr van Zyl takes a thoughtful, patient-centred approach, with clinical interests in women’s health, sexual health, aesthetic medicine and emergency care. She provides evidence-based, personalised care that considers the whole person and supports patients to take an active role in their wellbeing."
    ]
  }
];
function doctorProfileCard(i, portrait, classes='') {
  const {name,credentials,training,bio}=doctorProfiles[i];
  const more=bio.length ? `<details><summary>Read more</summary><ul>${training.map(q=>`<li>${q}</li>`).join('')}</ul>${bio.map(p=>`<p>${p}</p>`).join('')}</details>` : '';
  return `<article class="doctor-profile ${classes}">${portrait}<small>Partner doctor</small><h3>${name}</h3><p>${credentials}</p>${more}</article>`;
}

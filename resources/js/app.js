import './bootstrap';
import Alpine from 'alpinejs';

// How It Works interactive stepper
window.howItWorks = function() {
    return {
        current: 0,
        steps: [
            {
                title: 'Sign Up & Choose Your Treatment',
                description: 'Create your free account in seconds. Browse our treatments and pick what you need help with.',
                details: [
                    'Quick registration — email + password',
                    'Browse treatments: weight loss, skincare, sexual health, etc.',
                    'Select the treatment that fits your situation',
                ],
                time: 'Takes about 1 minute',
                image: '/images/Sign_up.webp',
            },
            {
                title: 'Complete Your Health Assessment',
                description: 'Answer a short, confidential questionnaire so your doctor has full context before your consultation.',
                details: [
                    '5-8 questions — takes under 2 minutes',
                    'Questions adapt based on your answers',
                    'Choose your consultation type: video, audio, or text',
                    '100% confidential — only your doctor sees this',
                ],
                time: 'Takes about 2 minutes',
                image: '/images/Sign_up.webp',
            },
            {
                title: 'Pay for Your Consultation',
                description: 'Secure payment for your doctor consultation via PayFast. Then choose a time slot that works for you.',
                details: [
                    'Card, EFT, or SnapScan accepted',
                    'From R450 once-off or R220/month subscription',
                    'Pick a time that suits you',
                    'Get confirmation via email & SMS',
                ],
                time: 'Takes about 1 minute',
                image: '/images/Sign_up.webp',
            },
            {
                title: 'See Your Doctor',
                description: 'Your consultation happens via video, audio, or text — your choice. The doctor reviews your assessment beforehand.',
                details: [
                    'Doctor already has your health context',
                    '15-30 minute consultation',
                    'Discuss symptoms, get diagnosed, agree on treatment',
                    'Doctor writes your prescription on the spot',
                ],
                time: '15-30 minutes',
                image: '/images/Sign_up.webp',
            },
            {
                title: 'Prescription & Medication Payment',
                description: 'Your doctor digitally signs your prescription. Pay for your medication separately — transparent pricing, no surprises.',
                details: [
                    'E-prescription sent directly to pharmacy partner',
                    'You see medication cost upfront before paying',
                    'Pay securely online via PayFast',
                    'Pharmacy prepares your order same day',
                ],
                time: 'Same day processing',
                image: '/images/Sign_up.webp',
            },
            {
                title: 'Medicine Delivered to Your Door',
                description: 'Discreet, unbranded packaging. Delivered nationwide in 1-3 business days. No one knows what\'s inside.',
                details: [
                    'Plain packaging — completely discreet',
                    'Track your delivery in real-time',
                    'All 9 provinces covered',
                    'Ongoing support from your care team',
                ],
                time: '1-3 business days delivery',
                image: '/images/Sign_up.webp',
            },
        ],
    };
};

// Assessment form with conditional logic
window.assessmentForm = function(questions) {
    return {
        questions: questions,
        answers: {},

        updateAnswer(questionId, value) {
            this.answers[questionId] = value;
        },

        toggleCheckbox(questionId, value, checked) {
            if (!this.answers[questionId]) {
                this.answers[questionId] = [];
            }
            if (checked) {
                if (!this.answers[questionId].includes(value)) {
                    this.answers[questionId].push(value);
                }
            } else {
                this.answers[questionId] = this.answers[questionId].filter(v => v !== value);
            }
        },

        isVisible(questionId) {
            const question = this.questions.find(q => q.id === questionId);
            if (!question || !question.show_if) return true;

            // Check all conditions (AND logic)
            for (const [depId, requiredValue] of Object.entries(question.show_if)) {
                const currentAnswer = this.answers[depId];

                if (currentAnswer === undefined || currentAnswer === null || currentAnswer === '') {
                    return false;
                }

                // For checkbox answers (arrays), check if the required value is in the array
                if (Array.isArray(currentAnswer)) {
                    if (!currentAnswer.includes(requiredValue)) {
                        return false;
                    }
                } else {
                    // For radio/select/text, exact match
                    if (currentAnswer !== requiredValue) {
                        return false;
                    }
                }
            }

            return true;
        }
    };
};

// Consultation countdown timer component
window.consultationTimer = function(deadline, durationMinutes) {
    return {
        deadline: new Date(deadline),
        durationMinutes: durationMinutes,
        totalSeconds: durationMinutes * 60,
        remaining: 0,
        display: '--:--',
        status: 'ok', // 'ok', 'warning', 'overtime'
        progress: 0,
        interval: null,

        startTimer() {
            this.tick();
            this.interval = setInterval(() => this.tick(), 1000);
        },

        tick() {
            const now = new Date();
            const diff = Math.floor((this.deadline - now) / 1000);
            this.remaining = diff;

            if (diff > 300) {
                // More than 5 minutes left
                this.status = 'ok';
                this.display = this.formatTime(diff);
            } else if (diff > 0) {
                // Under 5 minutes — warning
                this.status = 'warning';
                this.display = this.formatTime(diff);
            } else {
                // Overtime
                this.status = 'overtime';
                this.display = '+' + this.formatTime(Math.abs(diff));
            }

            // Progress: how much time has elapsed as percentage of total
            const elapsed = this.totalSeconds - diff;
            this.progress = Math.max(0, (elapsed / this.totalSeconds) * 100);
        },

        formatTime(seconds) {
            const mins = Math.floor(seconds / 60);
            const secs = seconds % 60;
            return String(mins).padStart(2, '0') + ':' + String(secs).padStart(2, '0');
        },

        destroy() {
            if (this.interval) clearInterval(this.interval);
        }
    };
};

// Only start Alpine if Livewire hasn't already started it
if (!window.Alpine) {
    window.Alpine = Alpine;
    Alpine.start();
}

<?php

namespace Database\Seeders;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BlogPostSeeder extends Seeder
{
    public function run(): void
    {
        $categories = BlogCategory::all()->keyBy('slug');

        $posts = [
            ['title' => 'Celebrating Women\'s Health with Zapmed this Women\'s Day', 'category' => 'womens-health', 'excerpt' => 'August 9th is a special day in South Africa as we celebrate Women\'s Day—a day dedicated to honoring the strength, achievements, and contributions of women across the nation. At Zapmed, we\'re excited to join in this celebration by focusing on women\'s health.'],
            ['title' => 'Your August Health Hack: 6 Tips for Better Living', 'category' => 'general-health', 'excerpt' => 'A woman having a virtual consultation with a doctor, referencing discussing health hacks and treatments for health conditions such as colds and flus.'],
            ['title' => 'Embracing Self-Pleasure: The Mental Health Benefits You Need To Know', 'category' => 'sexual-health', 'excerpt' => 'Self-pleasure, also known as masturbation, is a natural and normal part of human sexuality. Yet, many individuals feel embarrassed or ashamed to discuss it openly due to societal norms and cultural taboos.'],
            ['title' => 'Protein vs. Amino Acids: What\'s the Difference and Do You Need Both?', 'category' => 'weight-loss', 'excerpt' => 'This article was written in collaboration with Bare Aminos. Over the last two weeks, we\'ve built the case: muscle is your metabolism\'s best friend, protecting it during weight loss is non-negotiable.'],
            ['title' => 'How to Hit Your Proteins When Your Appetite\'s Gone', 'category' => 'weight-loss', 'excerpt' => 'This article was written in collaboration with Bare Aminos. Last week we made the case that muscle is your metabolism\'s best friend, and that protein is one of the two levers that protect it during weight loss.'],
            ['title' => 'Why Muscle is Your Metabolism\'s Best Friend', 'category' => 'weight-loss', 'excerpt' => 'If you\'re losing weight, especially on a medically guided programme, the number on the scale is only half the story. The other half is what you\'re losing.'],
            ['title' => 'What Causes Acne and How to Treat It Online', 'category' => 'skincare', 'excerpt' => 'Acne is one of the most common skin conditions, affecting teenagers and adults alike. While many people expect breakouts to disappear after their teenage years, acne often continues well into adulthood.'],
            ['title' => 'Signs of Low Testosterone in South African Men', 'category' => 'mens-health', 'excerpt' => 'You wake up tired even after eight hours of sleep. Your motivation feels flat, your gym sessions aren\'t hitting the same, and your mood has been all over the place lately.'],
            ['title' => 'The Starter\'s Guide to Contraceptive Pills in South Africa', 'category' => 'womens-health', 'excerpt' => 'Every woman who chooses a method of family planning has her own reasons for doing so. Some want to plan when to grow their family, on their own terms and in their own time.'],
            ['title' => 'Can Your Relaxing Bubble Bath Secretly Cause Thrush?', 'category' => 'womens-health', 'excerpt' => 'A warm bubble bath feels like the perfect way to unwind, and most of us do not think twice about the products we pour in. But if you have noticed itching, irritation or unusual discharge after a soak, your bath routine may be worth a second look.'],
            ['title' => 'Genital Warts Treatment: What to Expect, What it Costs', 'category' => 'sexual-health', 'excerpt' => 'Few health concerns send people down an internet rabbit hole quite like finding an unfamiliar bump in an intimate area. You look once, then again.'],
            ['title' => 'Payment Troubleshooting, Failed Cards, Debit Orders and Refunds', 'category' => 'general-health', 'excerpt' => 'There are few things more frustrating than a payment notification that catches you by surprise. It is your money, you have planned for where it needs to go.'],
            ['title' => 'Can You Get a 3-Month Supply of Birth Control?', 'category' => 'womens-health', 'excerpt' => 'At Zapmed, we are focused on making wellness feel simpler, more accessible, and easier to fit into everyday life. Convenience matters to us.'],
            ['title' => 'What Zapmed treats (and what we don\'t)', 'category' => 'general-health', 'excerpt' => 'Being able to speak to a medical professional from your own home, at a time that suits you, without sitting in a waiting room or taking half a day off work is the heart of what telehealth offers.'],
            ['title' => 'Ex-Contro Member? Here\'s How to Reactivate on Zapmed', 'category' => 'general-health', 'excerpt' => 'If you previously relied on the medical expertise at Contro, we want to start by reassuring you that the high standard of clinical care you value remains our top priority.'],
            ['title' => 'How to Access Your Zapmed Invoice to Claim from Your Medical Aid', 'category' => 'general-health', 'excerpt' => 'Living in South Africa, we all know that the true value of a medical aid lies in its ability to cushion the blow of healthcare costs.'],
            ['title' => 'Where Are My Meds? A Simple Guide to Zapmed Delivery Tracking', 'category' => 'general-health', 'excerpt' => 'When you hand over something as precious as your health to a telehealth service, you\'re placing a lot of trust in that service. That\'s not something we take lightly at Zapmed.'],
            ['title' => 'How Much Does Mounjaro Cost in South Africa? A Transparent GLP-1 Price Comparison', 'category' => 'weight-loss', 'excerpt' => 'Recently, you\'ve probably heard the buzz around GLP-1 medications—the highly effective weight-loss injections that everyone from Hollywood A-listers to your gym buddy seems to be talking about.'],
            ['title' => 'Who is Discreet Online STI Treatment in South Africa For?', 'category' => 'sexual-health', 'excerpt' => 'Getting online STI treatment has become more accessible than ever, with platforms such as Zapmed making it possible to consult a qualified health professional.'],
            ['title' => '3 Life Stages When a Sexual Health Screening for Couples is Most Important', 'category' => 'sexual-health', 'excerpt' => 'Sexual health is a shared responsibility in any relationship, and screenings are not just for when something feels wrong. For couples, getting tested together builds trust.'],
            ['title' => '4 Common Myths About Hair Loss Treatment in South Africa', 'category' => 'general-health', 'excerpt' => 'Hair loss treatment in South Africa is often misunderstood, leading to frustration and poor decisions. The most effective approach is early action, evidence-based treatment, and realistic expectations.'],
            ['title' => 'Four Key Differences Between Wegovy and Mounjaro for Medical Weight Loss in South Africa', 'category' => 'weight-loss', 'excerpt' => 'Obesity treatment in South Africa doesn\'t look the way it did ten years ago. The conversation about weight—long dominated by diets, willpower, and shame—now often ends with a prescription.'],
            ['title' => '5 Things to Expect When Starting Erectile Dysfunction Treatment in South Africa', 'category' => 'mens-health', 'excerpt' => 'The National Library of Medicine projects that around 150 million men worldwide are living with erectile dysfunction (ED). It is a figure that reflects the scale of this condition.'],
            ['title' => 'How to Prepare for Your First Online Doctor Consultation in South Africa', 'category' => 'general-health', 'excerpt' => 'For anyone who has spent hours in a waiting room only to see a doctor for ten minutes, telehealth makes a lot of sense. South Africans discovered this during the pandemic.'],
            ['title' => 'Winter Skin Woes? How to Keep Your Skin Hydrated & Healthy in Cold Weather', 'category' => 'skincare', 'excerpt' => 'As the temperatures drop and we swap iced lattes for hot chocolate, there\'s one thing many of us forget—our skin needs extra love during winter.'],
            ['title' => 'Why Your Gut Health Matters & How It Affects Your Skin, Mood & More', 'category' => 'general-health', 'excerpt' => 'You know that "gut feeling" people always talk about? Turns out, it\'s not just a figure of speech—your gut health literally affects everything.'],
            ['title' => 'Burnout Is Real: Signs You Need a Break & How to Recharge', 'category' => 'general-health', 'excerpt' => 'We\'ve all been there—running on empty, exhausted, and feeling like life is one never-ending to-do list. Between work, responsibilities, relationships, and just trying to keep it all together.'],
            ['title' => 'Birth Control 101: Finding the Right Contraceptive for You in South Africa', 'category' => 'womens-health', 'excerpt' => 'Valentine\'s Day is all about love, romance, and feeling yourself—but let\'s be real: nothing kills the vibe faster than a pregnancy scare or worrying about your reproductive health.'],
            ['title' => 'Breast Cancer Awareness Month: The Power of Early Detection and Self-Examination', 'category' => 'womens-health', 'excerpt' => 'Breast cancer is the most common cancer among women worldwide, and while it can be a daunting topic, awareness and education are our strongest allies in the fight against it.'],
            ['title' => 'Understanding Your Skin Type: Why It Matters and How to Identify Yours', 'category' => 'skincare', 'excerpt' => 'Your skin is more than just a barrier; it\'s a reflection of your overall health and well-being. It\'s crucial to understand the different skin types and why knowing your own skin type can lead to better skincare decisions.'],
            ['title' => 'Zapmed\'s Comprehensive Approach to Acne Treatment', 'category' => 'skincare', 'excerpt' => 'Acne – the mere mention of this word can evoke feelings of frustration and insecurity in many individuals. Whether you\'re dealing with occasional breakouts or battling persistent acne.'],
            ['title' => 'What are the Top Ten Benefits of Sex?', 'category' => 'sexual-health', 'excerpt' => 'February is the month of love and what better way to celebrate it than by talking about the numerous health benefits of sex. Sex and sexuality are an essential part of everyone\'s life.'],
            ['title' => 'Trichomoniasis: Everything You Need to Know', 'category' => 'sexual-health', 'excerpt' => 'Trichomoniasis (or "trich") is an STI which is often present alongside other STIs and is caused by the parasite Trichomonas vaginalis.'],
            ['title' => '10 Myths about the Contraceptive Pill', 'category' => 'womens-health', 'excerpt' => 'When researching birth control, it is important that you use reliable sources for information. Every pill has a different effect on every female body.'],
            ['title' => 'Breaking Down the Outrageous Pink Tax: Unveiling Gender-Based Pricing Disparities', 'category' => 'womens-health', 'excerpt' => 'Pink Tax is a term that describes that products and services specifically targeted toward women tend to be more expensive than the nearly identical products and services for men.'],
        ];

        foreach ($posts as $index => $postData) {
            $cat = $categories[$postData['category']] ?? $categories->first();

            BlogPost::firstOrCreate(
                ['slug' => Str::slug($postData['title'])],
                [
                    'blog_category_id' => $cat->id,
                    'title' => $postData['title'],
                    'slug' => Str::slug($postData['title']),
                    'excerpt' => $postData['excerpt'],
                    'body' => '<p>' . $postData['excerpt'] . '</p><p>Full article content to be migrated from zapmed.co.za. Please update this post with the complete content.</p>',
                    'status' => 'published',
                    'published_at' => now()->subDays(count($posts) - $index),
                    'reading_time' => rand(3, 8),
                ]
            );
        }
    }
}

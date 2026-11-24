<?php

namespace Database\Seeders;

use App\Models\Recipe;
use Illuminate\Database\Seeder;

/**
 * 20 SAMPLE recipes (5 per meal slot) so the recipes and meal plan screens work before Amy supplies the real set.
 * Marked source = demo and shown to members as "Sample recipe". The nutrition values are rough estimates and have
 * NOT been checked by a dietitian. Replace them in the admin recipe editor, then switch these off.
 *
 * Safe to run again: matches on name, never creates duplicates, never touches recipes that are not demo.
 */
class CoachDemoRecipeSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->recipes() as $r) {
            $existing = Recipe::where('name', $r['name'])->first();
            if ($existing && ! $existing->isDemo()) {
                continue;
            }

            Recipe::updateOrCreate(['name' => $r['name'], 'source' => 'demo'], $r + [
                'source' => 'demo',
                'is_active' => true,
                'approved_at' => now(),
            ]);
        }
    }

    /** @return array<int,array<string,mixed>> */
    private function recipes(): array
    {
        $all = ['vegetarian', 'vegan', 'pescatarian', 'halal'];
        $veg = ['vegetarian', 'pescatarian', 'halal'];

        return [
            // Breakfast
            $this->r('breakfast', 'Greek yoghurt berry bowl', 5, 280, 24, 5, 28, 8, 14, $veg, ['dairy'], ['dairy'], '#DCE5DA', '🍓',
                [['Plain Greek yoghurt', 200], ['Mixed berries', 80], ['Chia seeds', 10]],
                ['Spoon the yoghurt into a bowl.', 'Top with the berries.', 'Sprinkle over the chia seeds.']),
            $this->r('breakfast', 'Veggie omelette', 10, 310, 26, 3, 6, 20, 3, $veg, ['eggs'], ['eggs'], '#F4E7D2', '🍳',
                [['Eggs', 150], ['Spinach', 40], ['Tomato', 60], ['Olive oil', 5]],
                ['Whisk the eggs with a pinch of salt.', 'Soften the spinach and tomato in the oil.', 'Pour in the eggs and cook until set, then fold.']),
            $this->r('breakfast', 'Overnight oats with chia', 5, 330, 18, 9, 42, 10, 9, $all, ['gluten', 'nuts'], [], '#EDE3D2', '🥣',
                [['Rolled oats', 40], ['Almond milk, unsweetened', 150], ['Chia seeds', 15], ['Blueberries', 50]],
                ['Stir the oats, almond milk and chia together.', 'Leave in the fridge overnight.', 'Top with blueberries and serve cold.']),
            $this->r('breakfast', 'Smoked salmon and scrambled eggs on rye', 10, 360, 31, 4, 22, 18, 2, ['pescatarian', 'halal'], ['fish', 'eggs', 'gluten'], ['fish', 'eggs'], '#E8DCE0', '🐟',
                [['Eggs', 100], ['Smoked salmon', 60], ['Rye bread', 40], ['Butter', 5]],
                ['Scramble the eggs gently in the butter.', 'Toast the rye.', 'Pile the eggs and salmon on the toast.']),
            $this->r('breakfast', 'Cottage cheese and tomato toast', 5, 290, 25, 5, 24, 10, 5, $veg, ['dairy', 'gluten'], ['dairy'], '#D6E6E7', '🍅',
                [['Cottage cheese', 120], ['Wholewheat bread', 40], ['Tomato', 80], ['Black pepper', 1]],
                ['Toast the bread.', 'Spread with the cottage cheese.', 'Top with sliced tomato and black pepper.']),

            // Lunch
            $this->r('lunch', 'Chicken and quinoa salad', 15, 420, 38, 7, 30, 14, 4, ['halal'], [], ['chicken'], '#D6E6E7', '🥗',
                [['Grilled chicken breast', 120], ['Cooked quinoa', 100], ['Cucumber', 60], ['Cherry tomatoes', 60], ['Olive oil', 8]],
                ['Slice the cooked chicken.', 'Toss with the quinoa, cucumber and tomatoes.', 'Dress with olive oil and lemon.']),
            $this->r('lunch', 'Tuna avo bowl', 10, 410, 34, 8, 22, 20, 2, ['pescatarian', 'halal'], ['fish'], ['fish'], '#DCE5DA', '🥑',
                [['Tuna in water, drained', 110], ['Avocado', 70], ['Mixed leaves', 50], ['Cucumber', 60], ['Lemon juice', 10]],
                ['Flake the tuna into a bowl.', 'Add the avocado, leaves and cucumber.', 'Squeeze over the lemon juice.']),
            $this->r('lunch', 'Lentil and roasted veg soup', 35, 380, 22, 14, 50, 7, 9, $all, [], [], '#F4E7D2', '🍲',
                [['Cooked lentils', 150], ['Carrot', 80], ['Butternut', 100], ['Onion', 50], ['Vegetable stock', 300]],
                ['Roast the carrot, butternut and onion until soft.', 'Simmer with the lentils and stock for 10 minutes.', 'Blend half the soup for a thicker texture.']),
            $this->r('lunch', 'Lean beef and veg stir-fry', 20, 430, 36, 6, 26, 18, 6, ['halal'], ['soy'], ['red_meat'], '#E8DCE0', '🥩',
                [['Lean beef strips', 120], ['Broccoli', 80], ['Baby marrow', 60], ['Soy sauce', 10], ['Garlic', 5]],
                ['Sear the beef in a hot pan.', 'Add the vegetables and garlic.', 'Finish with the soy sauce.']),
            $this->r('lunch', 'Egg and chickpea wrap', 10, 400, 24, 10, 44, 13, 4, $veg, ['eggs', 'gluten'], ['eggs'], '#EDE3D2', '🌯',
                [['Wholewheat wrap', 60], ['Boiled egg', 60], ['Chickpeas', 80], ['Lettuce', 30], ['Plain yoghurt', 30]],
                ['Mash the egg and chickpeas with the yoghurt.', 'Spoon onto the wrap with the lettuce.', 'Roll up and slice in half.']),

            // Dinner
            $this->r('dinner', 'Grilled hake and veg', 25, 450, 40, 6, 24, 20, 4, ['pescatarian', 'halal'], ['fish'], ['fish'], '#D6E6E7', '🐟',
                [['Hake fillet', 160], ['Green beans', 100], ['Cherry tomatoes', 80], ['Olive oil', 10], ['Lemon', 20]],
                ['Season the hake and grill for 8 minutes.', 'Steam the beans and tomatoes.', 'Plate together with lemon and olive oil.']),
            $this->r('dinner', 'Chickpea curry', 30, 430, 22, 12, 48, 14, 9, $all, [], ['spicy'], '#F4E7D2', '🍛',
                [['Chickpeas', 150], ['Tinned tomato', 120], ['Onion', 50], ['Curry paste', 15], ['Spinach', 50]],
                ['Fry the onion and curry paste.', 'Add the tomato and chickpeas and simmer for 15 minutes.', 'Stir through the spinach.']),
            $this->r('dinner', 'Lemon chicken with roasted vegetables', 40, 440, 42, 6, 20, 19, 5, ['halal'], [], ['chicken'], '#DCE5DA', '🍗',
                [['Chicken breast', 160], ['Courgette', 100], ['Red pepper', 80], ['Olive oil', 10], ['Lemon', 30]],
                ['Marinate the chicken in lemon and oil.', 'Roast with the vegetables for 30 minutes.', 'Rest for 5 minutes and serve.']),
            $this->r('dinner', 'Turkey meatballs with courgetti', 30, 410, 38, 5, 16, 20, 6, ['halal'], ['eggs'], ['eggs'], '#E8DCE0', '🍝',
                [['Lean turkey mince', 150], ['Egg', 25], ['Courgette, spiralised', 200], ['Tinned tomato', 100], ['Garlic', 5]],
                ['Mix the mince, egg and garlic and roll into balls.', 'Bake for 20 minutes.', 'Warm the courgetti in the tomato sauce and add the meatballs.']),
            $this->r('dinner', 'Tofu and broccoli stir-fry', 20, 380, 31, 8, 24, 18, 5, $all, ['soy'], [], '#EDE3D2', '🥦',
                [['Firm tofu', 180], ['Broccoli', 120], ['Ginger', 5], ['Soy sauce', 10], ['Sesame oil', 5]],
                ['Press and cube the tofu, then fry until golden.', 'Add the broccoli and ginger.', 'Finish with soy sauce and sesame oil.']),

            // Snack
            $this->r('snack', 'Biltong and cucumber', 2, 120, 18, 1, 3, 4, 2, ['halal'], [], ['red_meat'], '#E8DCE0', '🥒',
                [['Lean biltong', 35], ['Cucumber', 100]],
                ['Slice the cucumber.', 'Serve with the biltong.']),
            $this->r('snack', 'Whey protein shake', 3, 160, 24, 1, 6, 3, 4, $veg, ['dairy'], ['dairy'], '#D6E6E7', '🥤',
                [['Whey protein powder', 30], ['Water or milk alternative', 250]],
                ['Shake the powder with the liquid until smooth.']),
            $this->r('snack', 'Apple with almond butter', 2, 190, 5, 5, 24, 9, 17, $all, ['nuts'], [], '#F4E7D2', '🍎',
                [['Apple', 150], ['Almond butter', 15]],
                ['Slice the apple.', 'Serve with the almond butter.']),
            $this->r('snack', 'Boiled eggs and cherry tomatoes', 10, 140, 13, 1, 4, 9, 3, $veg, ['eggs'], ['eggs'], '#DCE5DA', '🥚',
                [['Eggs', 100], ['Cherry tomatoes', 80]],
                ['Boil the eggs for 8 minutes and cool.', 'Peel and serve with the tomatoes.']),
            $this->r('snack', 'Hummus and veg sticks', 5, 150, 6, 6, 16, 7, 4, $all, [], [], '#EDE3D2', '🥕',
                [['Hummus', 50], ['Carrot', 60], ['Celery', 50], ['Cucumber', 50]],
                ['Cut the vegetables into sticks.', 'Serve with the hummus.']),
        ];
    }

    private function r(string $slot, string $name, int $minutes, int $kcal, float $protein, float $fibre, float $carbs, float $fat, float $sugars,
        array $diets, array $allergens, array $avoids, string $colour, string $emoji, array $ingredients, array $steps): array
    {
        return [
            'slot' => $slot, 'name' => $name, 'minutes' => $minutes, 'kcal' => $kcal,
            'protein_g' => $protein, 'fibre_g' => $fibre, 'carbs_g' => $carbs, 'fat_g' => $fat, 'sugars_g' => $sugars,
            'diet_tags' => $diets, 'allergens' => $allergens, 'avoid_tags' => $avoids, 'colour' => $colour, 'emoji' => $emoji,
            'ingredients' => array_map(fn ($i) => ['name' => $i[0], 'grams' => $i[1]], $ingredients),
            'steps' => $steps,
        ];
    }
}

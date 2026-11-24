<?php

namespace App\Livewire\Admin;

use App\Enums\UserRole;
use App\Models\Recipe;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Recipe editor for the Coach App (admin and health coaches). Amy owns the real recipes and nutrition values.
 *
 * Rules:
 *  - Any save clears the approval: a changed recipe must be checked again before members see it.
 *  - Only a health coach (the dietitian) can approve. Admin can edit, switch on/off and delete, but cannot approve.
 *  - An approved recipe is switched off rather than deleted (deleting removes it from member plans and favourites).
 */
class CoachRecipes extends Component
{
    use WithFileUploads;

    public string $filter = 'all';        // all | waiting | approved | off | demo

    public string $search = '';

    public ?int $editingId = null;

    public bool $showForm = false;

    public string $slot = 'breakfast';

    public string $name = '';

    public string $summary = '';

    public string $minutes = '15';

    public string $kcal = '';

    public string $protein_g = '';

    public string $fibre_g = '0';

    public string $carbs_g = '0';

    public string $fat_g = '0';

    public string $sugars_g = '0';

    public string $ingredients = '';      // one per line: "Chicken breast | 120"

    public string $steps = '';            // one per line

    /** @var array<int,string> */
    public array $diet_tags = [];

    /** @var array<int,string> */
    public array $allergens = [];

    /** @var array<int,string> */
    public array $avoid_tags = [];

    public bool $is_active = true;

    /** New photo chosen in the form (temporary upload). */
    public $photo = null;

    public ?string $currentPhoto = null;

    public bool $removePhoto = false;

    public function mount(): void
    {
        abort_unless($this->canEdit(), 403);
    }

    public function setFilter(string $filter): void
    {
        $this->filter = in_array($filter, ['all', 'waiting', 'approved', 'off', 'demo'], true) ? $filter : 'all';
    }

    public function create(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $r = Recipe::findOrFail($id);

        $this->resetForm();
        $this->editingId = $r->id;
        $this->slot = $r->slot;
        $this->name = $r->name;
        $this->summary = (string) $r->summary;
        $this->minutes = (string) $r->minutes;
        $this->kcal = (string) $r->kcal;
        $this->protein_g = (string) $r->protein_g;
        $this->fibre_g = (string) $r->fibre_g;
        $this->carbs_g = (string) $r->carbs_g;
        $this->fat_g = (string) $r->fat_g;
        $this->sugars_g = (string) $r->sugars_g;
        $this->ingredients = collect((array) $r->ingredients)->map(fn ($i) => $i['name'].' | '.$i['grams'])->implode("\n");
        $this->steps = implode("\n", (array) $r->steps);
        $this->diet_tags = (array) $r->diet_tags;
        $this->allergens = (array) $r->allergens;
        $this->avoid_tags = (array) $r->avoid_tags;
        $this->is_active = $r->is_active;
        $this->currentPhoto = $r->photoUrl();
        $this->showForm = true;
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    public function save(): void
    {
        abort_unless($this->canEdit(), 403);

        $this->validate([
            'slot' => ['required', Rule::in(Recipe::SLOTS)],
            'name' => ['required', 'string', 'max:120'],
            'summary' => ['nullable', 'string', 'max:280'],
            'minutes' => ['required', 'integer', 'min:1', 'max:600'],
            'kcal' => ['required', 'integer', 'min:0', 'max:3000'],
            'protein_g' => ['required', 'numeric', 'min:0', 'max:300'],
            'fibre_g' => ['required', 'numeric', 'min:0', 'max:100'],
            'carbs_g' => ['required', 'numeric', 'min:0', 'max:500'],
            'fat_g' => ['required', 'numeric', 'min:0', 'max:300'],
            'sugars_g' => ['required', 'numeric', 'min:0', 'max:300'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.config('coach.recipes.photo_max_kb', 2048)],
            'diet_tags.*' => [Rule::in(array_keys(config('coach.recipes.diet_tags')))],
            'allergens.*' => [Rule::in(array_keys(config('coach.profile.allergies')))],
            'avoid_tags.*' => [Rule::in(array_keys(config('coach.profile.avoids')))],
        ]);

        $ingredients = $this->parseIngredients();
        if ($this->getErrorBag()->has('ingredients')) {
            return;
        }
        $steps = array_values(array_filter(array_map('trim', preg_split('/\R/', $this->steps) ?: [])));
        if ($ingredients === []) {
            $this->addError('ingredients', 'Add at least one ingredient, one per line, like "Chicken breast | 120".');

            return;
        }
        if ($steps === []) {
            $this->addError('steps', 'Add at least one step, one per line.');

            return;
        }

        $data = [
            'slot' => $this->slot, 'name' => trim($this->name), 'summary' => trim($this->summary) ?: null,
            'minutes' => (int) $this->minutes, 'kcal' => (int) $this->kcal,
            'protein_g' => (float) $this->protein_g, 'fibre_g' => (float) $this->fibre_g, 'carbs_g' => (float) $this->carbs_g,
            'fat_g' => (float) $this->fat_g, 'sugars_g' => (float) $this->sugars_g,
            'ingredients' => $ingredients, 'steps' => $steps,
            'diet_tags' => array_values($this->diet_tags), 'allergens' => array_values($this->allergens), 'avoid_tags' => array_values($this->avoid_tags),
            'is_active' => $this->is_active,
            // Any change needs checking again.
            'approved_by' => null, 'approved_at' => null,
        ];

        $disk = config('coach.recipes.photo_disk');
        $newPath = $this->photo ? $this->photo->store('recipes', $disk) : null;

        if ($this->editingId) {
            $recipe = Recipe::findOrFail($this->editingId);
            $old = $recipe->image_path;
            if ($newPath) {
                $data['image_path'] = $newPath;
            } elseif ($this->removePhoto) {
                $data['image_path'] = null;
            }
            // Editing a sample recipe turns it into a real one.
            $recipe->update($data + ['source' => $recipe->isDemo() ? 'amy' : $recipe->source]);
            if ($old && ($newPath || $this->removePhoto)) {
                Storage::disk($disk)->delete($old);
            }
        } else {
            Recipe::create($data + ['source' => 'amy', 'created_by' => Auth::id(), 'image_path' => $newPath]);
        }

        session()->flash('message', 'Recipe saved. It needs approval by a dietitian before members see it.');
        $this->resetForm();
    }

    public function approve(int $id): void
    {
        abort_unless(Auth::user()->role === UserRole::HealthCoach, 403);

        Recipe::findOrFail($id)->update(['approved_by' => Auth::id(), 'approved_at' => now()]);
        session()->flash('message', 'Recipe approved. Members can see it now.');
    }

    public function toggleActive(int $id): void
    {
        abort_unless($this->canEdit(), 403);

        $r = Recipe::findOrFail($id);
        $r->update(['is_active' => ! $r->is_active]);
    }

    public function delete(int $id): void
    {
        abort_unless($this->canEdit(), 403);

        $r = Recipe::findOrFail($id);
        if ($r->isApproved() && ! $r->isDemo()) {
            session()->flash('message', 'An approved recipe is switched off, not deleted.');

            return;
        }

        if ($r->image_path) {
            Storage::disk(config('coach.recipes.photo_disk'))->delete($r->image_path);
        }
        $r->delete();
        session()->flash('message', 'Recipe deleted.');
    }

    public function render()
    {
        $recipes = Recipe::query()
            ->when($this->search !== '', fn ($q) => $q->where('name', 'like', '%'.$this->search.'%'))
            ->when($this->filter === 'waiting', fn ($q) => $q->whereNull('approved_at'))
            ->when($this->filter === 'approved', fn ($q) => $q->whereNotNull('approved_at'))
            ->when($this->filter === 'off', fn ($q) => $q->where('is_active', false))
            ->when($this->filter === 'demo', fn ($q) => $q->where('source', 'demo'))
            ->orderBy('slot')->orderBy('name')
            ->get();

        return view('livewire.admin.coach-recipes', [
            'recipes' => $recipes,
            'canApprove' => Auth::user()->role === UserRole::HealthCoach,
            'slots' => config('coach.recipes.slots'),
            'dietOptions' => config('coach.recipes.diet_tags'),
            'allergyOptions' => config('coach.profile.allergies'),
            'avoidOptions' => config('coach.profile.avoids'),
        ]);
    }

    private function canEdit(): bool
    {
        return in_array(Auth::user()?->role, [UserRole::Admin, UserRole::HealthCoach], true);
    }

    /** @return array<int,array{name:string,grams:float}> */
    private function parseIngredients(): array
    {
        $out = [];
        foreach (preg_split('/\R/', $this->ingredients) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (! preg_match('/^(.+?)\s*\|\s*(\d+(?:[.,]\d+)?)$/u', $line, $m)) {
                $this->addError('ingredients', "Line \"{$line}\" should look like: Chicken breast | 120");

                return [];
            }
            $out[] = ['name' => trim($m[1]), 'grams' => (float) str_replace(',', '.', $m[2])];
        }

        return $out;
    }

    private function resetForm(): void
    {
        $this->resetErrorBag();
        $this->reset([
            'editingId', 'showForm', 'slot', 'name', 'summary', 'minutes', 'kcal', 'protein_g', 'fibre_g', 'carbs_g',
            'fat_g', 'sugars_g', 'ingredients', 'steps', 'diet_tags', 'allergens', 'avoid_tags', 'is_active',
            'photo', 'currentPhoto', 'removePhoto',
        ]);
    }
}

<?php

use App\Models\AuditLog;
use App\Models\Department;
use App\Models\RequestCategory;
use App\Models\Team;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->c = cast();
});

describe('who may shape reference data', function () {
    // Everyone who is not an administrator, including management, which is observe-only.
    $outsiders = ['requester', 'ahmad', 'creativeLead', 'management'];

    it('lets only an administrator open the screens', function () use ($outsiders) {
        foreach (['/admin/teams', '/admin/categories', '/admin/departments'] as $url) {
            $this->actingAs($this->c->admin)->get($url)->assertOk();

            foreach ($outsiders as $who) {
                $this->actingAs($this->c->{$who})->get($url)->assertForbidden();
            }
        }
    });

    it('refuses writes from everyone but an administrator', function () use ($outsiders) {
        foreach ($outsiders as $who) {
            $actor = $this->c->{$who};

            $this->actingAs($actor)->post('/admin/teams', ['name' => 'Tim Baru'])->assertForbidden();
            $this->actingAs($actor)->patch("/admin/teams/{$this->c->it->id}", ['is_active' => false])->assertForbidden();
            $this->actingAs($actor)->post('/admin/departments', ['name' => 'Keuangan'])->assertForbidden();
            $this->actingAs($actor)->patch("/admin/categories/{$this->c->category->id}", ['name_id' => 'Diubah'])->assertForbidden();
        }

        expect(Team::query()->where('name', 'Tim Baru')->exists())->toBeFalse()
            ->and($this->c->it->fresh()->is_active)->toBeTrue()
            ->and($this->c->category->fresh()->name_id)->toBe('Desain');
    });
});

describe('teams', function () {
    it('creates a team, active, and writes it to the audit trail', function () {
        $this->actingAs($this->c->admin)
            ->post('/admin/teams', ['name' => 'Humas Digital', 'description' => 'Konten dan media sosial'])
            ->assertRedirect();

        $team = Team::query()->where('name', 'Humas Digital')->sole();

        expect($team->is_active)->toBeTrue()
            ->and($team->description)->toBe('Konten dan media sosial');

        $entry = AuditLog::query()->where('subject_type', 'Team')->where('subject_id', $team->id)->sole();
        expect($entry->action)->toBe('TEAM_CREATED')
            ->and($entry->actor_id)->toBe($this->c->admin->id);
    });

    it('turns a blank description into nothing rather than an empty string', function () {
        $this->actingAs($this->c->admin)->post('/admin/teams', ['name' => 'Tanpa Keterangan', 'description' => '   ']);

        expect(Team::query()->where('name', 'Tanpa Keterangan')->sole()->description)->toBeNull();
    });

    it('rejects a duplicate name', function () {
        $this->actingAs($this->c->admin)
            ->post('/admin/teams', ['name' => 'Creative'])
            ->assertSessionHasErrors('name');

        expect(Team::query()->where('name', 'Creative')->count())->toBe(1);
    });

    it('renames a team and records only what changed', function () {
        $this->actingAs($this->c->admin)
            ->patch("/admin/teams/{$this->c->creative->id}", ['name' => 'Kreatif'])
            ->assertRedirect();

        expect($this->c->creative->fresh()->name)->toBe('Kreatif');

        $entry = AuditLog::query()->where('subject_type', 'Team')->where('action', 'TEAM_UPDATED')->sole();
        expect($entry->old_values)->toBe(['name' => 'Creative'])
            ->and($entry->new_values)->toBe(['name' => 'Kreatif']);
    });

    it('writes nothing to the audit trail when nothing actually changed', function () {
        $this->actingAs($this->c->admin)->patch("/admin/teams/{$this->c->creative->id}", ['name' => 'Creative']);

        expect(AuditLog::query()->where('subject_type', 'Team')->count())->toBe(0);
    });

    it('refuses to switch off a team that is the only active team of its workers', function () {
        // Dewi and the IT lead are in IT and nowhere else.
        $this->actingAs($this->c->admin)
            ->patch("/admin/teams/{$this->c->it->id}", ['is_active' => false])
            ->assertSessionHasErrors('is_active');

        expect($this->c->it->fresh()->is_active)->toBeTrue();
    });

    it('switches a team off once its workers have another active team', function () {
        // Give every IT person a second home, then IT may close.
        foreach ([$this->c->dewi, $this->c->itLead] as $person) {
            $person->teams()->attach($this->c->creative->id);
        }

        $this->actingAs($this->c->admin)
            ->patch("/admin/teams/{$this->c->it->id}", ['is_active' => false])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        expect($this->c->it->fresh()->is_active)->toBeFalse();
        expect(AuditLog::query()->where('subject_type', 'Team')->sole()->action)->toBe('TEAM_DEACTIVATED');
    });

    it('ignores inactive people when deciding whether a team may close', function () {
        User::query()->whereIn('id', [$this->c->dewi->id, $this->c->itLead->id])->update(['is_active' => false]);

        $this->actingAs($this->c->admin)
            ->patch("/admin/teams/{$this->c->it->id}", ['is_active' => false])
            ->assertSessionHasNoErrors();

        expect($this->c->it->fresh()->is_active)->toBeFalse();
    });

    it('can switch a team back on', function () {
        $team = Team::factory()->create(['name' => 'Arsip', 'is_active' => false]);

        $this->actingAs($this->c->admin)->patch("/admin/teams/{$team->id}", ['is_active' => true]);

        expect($team->fresh()->is_active)->toBeTrue();
        expect(AuditLog::query()->where('subject_id', $team->id)->sole()->action)->toBe('TEAM_ACTIVATED');
    });

    it('warns on the screen about the teams that cannot be closed', function () {
        $response = $this->actingAs($this->c->admin)->get('/admin/teams')->assertInertia(
            fn (Assert $page) => $page
                ->component('admin/teams')
                ->has('teams.data', 2)
                // Both teams are active, so they come alphabetically: Creative, then IT.
                ->where('teams.data.1.name', 'IT')
                ->where('teams.data.1.members_count', 2)
        );

        // Names are faker-generated, so compare as a set rather than in order.
        expect($response->viewData('page')['props']['teams']['data'][1]['stranded_members'])
            ->toEqualCanonicalizing(['Dewi', $this->c->itLead->name]);
    });
});

describe('categories', function () {
    it('derives the slug from the Indonesian name and puts the category last', function () {
        $this->actingAs($this->c->admin)
            ->post('/admin/categories', ['name_id' => 'IT Support', 'name_en' => 'IT Support'])
            ->assertRedirect();

        $category = RequestCategory::query()->where('name_id', 'IT Support')->sole();

        expect($category->slug)->toBe('it-support')
            ->and($category->is_active)->toBeTrue()
            ->and($category->sort_order)->toBe($this->c->category->sort_order + 1);
    });

    it('never lets two categories share a slug', function () {
        // 'Desain' already exists as the slug 'design', so collide on the derived slug instead.
        RequestCategory::factory()->create(['slug' => 'konten', 'name_id' => 'Konten lama', 'name_en' => 'Old content']);

        $this->actingAs($this->c->admin)->post('/admin/categories', ['name_id' => 'Konten', 'name_en' => 'Content']);

        expect(RequestCategory::query()->where('name_id', 'Konten')->sole()->slug)->toBe('konten-2');
    });

    it('ignores a slug the admin tries to send', function () {
        $this->actingAs($this->c->admin)->post('/admin/categories', [
            'name_id' => 'Video', 'name_en' => 'Video', 'slug' => 'dipaksa',
        ]);

        expect(RequestCategory::query()->where('name_id', 'Video')->sole()->slug)->toBe('video');
    });

    it('keeps the slug fixed when the names change', function () {
        $this->actingAs($this->c->admin)->patch("/admin/categories/{$this->c->category->id}", [
            'name_id' => 'Desain Grafis', 'name_en' => 'Graphic Design', 'slug' => 'desain-grafis',
        ])->assertRedirect();

        $category = $this->c->category->fresh();

        expect($category->slug)->toBe('design')
            ->and($category->name_id)->toBe('Desain Grafis')
            ->and($category->name_en)->toBe('Graphic Design');
    });

    it('edits the form guidance in both languages', function () {
        $this->actingAs($this->c->admin)->patch("/admin/categories/{$this->c->category->id}", [
            'guidance_id' => 'Sertakan ukuran dan tanggal pakai.',
            'guidance_en' => 'Include the size and the date needed.',
        ]);

        $category = $this->c->category->fresh();

        expect($category->guidance_id)->toBe('Sertakan ukuran dan tanggal pakai.')
            ->and($category->guidance_en)->toBe('Include the size and the date needed.');
    });

    it('refuses to switch off the last active category', function () {
        $this->actingAs($this->c->admin)
            ->patch("/admin/categories/{$this->c->category->id}", ['is_active' => false])
            ->assertSessionHasErrors('is_active');

        expect($this->c->category->fresh()->is_active)->toBeTrue();
    });

    it('switches a category off once another one is active', function () {
        RequestCategory::factory()->create(['slug' => 'konten', 'name_id' => 'Konten', 'name_en' => 'Content']);

        $this->actingAs($this->c->admin)
            ->patch("/admin/categories/{$this->c->category->id}", ['is_active' => false])
            ->assertSessionHasNoErrors();

        expect($this->c->category->fresh()->is_active)->toBeFalse();
    });

    it('keeps a switched-off category on the requests already filed under it', function () {
        RequestCategory::factory()->create(['slug' => 'konten', 'name_id' => 'Konten', 'name_en' => 'Content']);
        $request = makeRequest($this->c->requester, $this->c->category);

        $this->actingAs($this->c->admin)->patch("/admin/categories/{$this->c->category->id}", ['is_active' => false]);

        expect($request->fresh()->category_id)->toBe($this->c->category->id);
    });

    it('shows the admin how many requests each category carries', function () {
        makeRequest($this->c->requester, $this->c->category);

        $this->actingAs($this->c->admin)->get('/admin/categories')->assertInertia(
            fn (Assert $page) => $page
                ->component('admin/categories')
                ->where('activeCount', 1)
                ->where('categories.data.0.slug', 'design')
                ->where('categories.data.0.requests_count', 1)
        );
    });
});

describe('departments', function () {
    it('creates a department and records it', function () {
        $this->actingAs($this->c->admin)->post('/admin/departments', ['name' => 'Keuangan'])->assertRedirect();

        $department = Department::query()->where('name', 'Keuangan')->sole();

        expect($department->is_active)->toBeTrue();
        expect(AuditLog::query()->where('subject_id', $department->id)->where('subject_type', 'Department')->sole()->action)
            ->toBe('DEPARTMENT_CREATED');
    });

    it('rejects a duplicate name', function () {
        $this->actingAs($this->c->admin)->post('/admin/departments', ['name' => 'Humas'])->assertSessionHasErrors('name');
    });

    it('switches a department off without disturbing the people on it', function () {
        $this->actingAs($this->c->admin)
            ->patch("/admin/departments/{$this->c->department->id}", ['is_active' => false])
            ->assertSessionHasNoErrors();

        expect($this->c->department->fresh()->is_active)->toBeFalse()
            ->and($this->c->requester->fresh()->department_id)->toBe($this->c->department->id);
    });

    it('stops offering an inactive department to new people', function () {
        $this->c->department->update(['is_active' => false]);

        $this->actingAs($this->c->admin)->post('/admin/users', [
            'name' => 'Orang Baru', 'email' => 'baru@tazkia.ac.id',
            'roles' => ['REQUESTER'], 'department_id' => $this->c->department->id,
        ])->assertSessionHasErrors('department_id');
    });

    it('counts the people on each department', function () {
        $this->actingAs($this->c->admin)->get('/admin/departments')->assertInertia(
            fn (Assert $page) => $page
                ->component('admin/departments')
                ->where('departments.data.0.name', 'Humas')
                ->where('departments.data.0.users_count', 2)
        );
    });
});

describe('the audit trail of reference data', function () {
    it('cannot be rewritten', function () {
        $this->actingAs($this->c->admin)->post('/admin/departments', ['name' => 'Keuangan']);

        $entry = AuditLog::query()->where('subject_type', 'Department')->sole();

        expect(fn () => $entry->update(['action' => 'SOMETHING_ELSE']))->toThrow(Exception::class);
    });

    it('never records a search query or anything but the row itself', function () {
        $this->actingAs($this->c->admin)->get('/admin/teams?q=Creative')->assertOk();

        expect(AuditLog::query()->count())->toBe(0);
    });
});

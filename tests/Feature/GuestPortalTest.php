<?php

namespace Tests\Feature;

use App\Models\ExamApplication;
use App\Models\ExamSet;
use App\Models\ExamSubject;
use App\Models\ExamSubjectState;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GuestPortalTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $guest;

    private ExamSet $exam;

    private ExamSubject $subject;

    private string $packBase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::query()->create([
            'name' => 'Owner', 'email' => 'owner@example.com', 'password' => 'owner-password', 'role' => 'owner',
        ]);
        $this->guest = User::query()->create([
            'name' => 'Rafi Ahmed', 'email' => 'guest@example.com', 'password' => 'guest-pass-1', 'role' => 'guest',
        ]);

        // Unique slug so a local machine's real packs are never touched.
        $slug = 'gp-test-'.Str::lower(Str::random(8));
        $this->exam = ExamSet::query()->create(['slug' => $slug, 'title' => 'Test Exam', 'year' => 2026, 'sort_order' => 1]);
        $this->subject = $this->exam->subjects()->create(['slug' => 'math', 'title' => 'Math', 'sort_order' => 1]);

        $this->packBase = storage_path('app/private/exams/'.$this->owner->id.'/'.$slug);
        $current = $this->packBase.'/math/current';
        File::ensureDirectoryExists($current.'/practice');
        File::put($current.'/practice/M01-practice.md', "1. What is 2+2?\n(a) 3\n(b) 4\n(c) 5\n(d) 6\n\n2. What is 3+3?\n(a) 5\n(b) 7\n(c) 6\n(d) 8\n");
        File::put($current.'/practice/M01-answers.md', "1. (b)\n2. (c)\n");
        ExamSubjectState::query()->create([
            'user_id' => $this->owner->id,
            'exam_subject_id' => $this->subject->id,
            'storage_path' => 'exams/'.$this->owner->id.'/'.$slug.'/math/current',
            'file_count' => 2,
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->packBase);
        parent::tearDown();
    }

    private function approve(): void
    {
        ExamApplication::query()->create([
            'user_id' => $this->guest->id,
            'exam_set_id' => $this->exam->id,
            'status' => ExamApplication::APPROVED,
        ]);
    }

    public function test_guest_endpoints_reject_owner_and_owner_endpoints_reject_guest(): void
    {
        Sanctum::actingAs($this->owner);
        $this->getJson('/api/guest/profile')->assertForbidden();
        $this->getJson('/api/guest/exams')->assertForbidden();

        Sanctum::actingAs($this->guest);
        $this->getJson('/api/exam-applications')->assertForbidden();
    }

    public function test_guest_can_list_exams_and_apply_once(): void
    {
        Sanctum::actingAs($this->guest);

        $this->getJson('/api/guest/exams')
            ->assertOk()
            ->assertJsonPath('data.0.slug', $this->exam->slug)
            ->assertJsonPath('data.0.application', null)
            ->assertJsonPath('data.0.subjects.0.title', 'Math');

        $this->postJson("/api/guest/exams/{$this->exam->slug}/apply")
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending');

        // Applying again is a no-op, not a duplicate row.
        $this->postJson("/api/guest/exams/{$this->exam->slug}/apply")
            ->assertOk()
            ->assertJsonPath('data.status', 'pending');
        $this->assertSame(1, ExamApplication::query()->count());

        $this->getJson('/api/guest/exams')->assertJsonPath('data.0.application.status', 'pending');
    }

    public function test_exam_content_is_locked_until_approved(): void
    {
        Sanctum::actingAs($this->guest);
        $base = "/api/guest/exams/{$this->exam->slug}";

        // no application
        $this->getJson($base)->assertForbidden();
        $this->getJson("$base/subjects/math/practice/sets")->assertForbidden();
        $this->getJson("$base/subjects/math/content/summary")->assertForbidden();

        // pending
        $this->postJson("$base/apply")->assertCreated();
        $this->getJson("$base/subjects/math/practice/sets")->assertForbidden();
        $this->postJson("$base/subjects/math/practice/sets/practice_M01-practice/submit", ['answers' => ['1' => 'B']])
            ->assertForbidden();
    }

    public function test_owner_reviews_applications_and_guest_gets_access(): void
    {
        Sanctum::actingAs($this->guest);
        $this->postJson("/api/guest/exams/{$this->exam->slug}/apply")->assertCreated();

        Sanctum::actingAs($this->owner);
        $list = $this->getJson('/api/exam-applications')
            ->assertOk()
            ->assertJsonPath('meta.pending', 1)
            ->assertJsonPath('data.0.guest.email', 'guest@example.com')
            ->assertJsonPath('data.0.exam.title', 'Test Exam');
        $id = $list->json('data.0.id');

        $this->patchJson("/api/exam-applications/{$id}", ['status' => 'approved'])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');
        $this->assertNotNull(ExamApplication::query()->find($id)->decided_at);

        Sanctum::actingAs($this->guest);
        $this->getJson("/api/guest/exams/{$this->exam->slug}")
            ->assertOk()
            ->assertJsonPath('data.subjects.0.has_pack', true);

        // revoke
        Sanctum::actingAs($this->owner);
        $this->patchJson("/api/exam-applications/{$id}", ['status' => 'rejected'])->assertOk();
        Sanctum::actingAs($this->guest);
        $this->getJson("/api/guest/exams/{$this->exam->slug}")->assertForbidden();

        // rejected guests can re-apply
        $this->postJson("/api/guest/exams/{$this->exam->slug}/apply")
            ->assertOk()
            ->assertJsonPath('data.status', 'pending');
    }

    public function test_approved_guest_can_practice_and_see_analytics(): void
    {
        $this->approve();
        Sanctum::actingAs($this->guest);
        $base = "/api/guest/exams/{$this->exam->slug}/subjects/math";

        $sets = $this->getJson("$base/practice/sets")->assertOk();
        $key = $sets->json('data.sets.0.key');
        $this->assertNotEmpty($key);

        $this->getJson("$base/practice/sets/$key")
            ->assertOk()
            ->assertJsonCount(2, 'data.questions');

        $this->postJson("$base/practice/sets/$key/submit", ['answers' => ['1' => 'B', '2' => 'A']])
            ->assertOk()
            ->assertJsonPath('data.summary.correct', 1)
            ->assertJsonPath('data.summary.answered', 2);

        // Attempt is stored for the guest, not the owner.
        $this->assertDatabaseHas('exam_practice_attempts', ['user_id' => $this->guest->id, 'set_key' => $key]);
        $this->assertDatabaseMissing('exam_practice_attempts', ['user_id' => $this->owner->id]);

        $this->getJson("$base/practice/performance")
            ->assertOk()
            ->assertJsonPath('data.totals.attempts', 1);

        $this->getJson('/api/guest/analytics')
            ->assertOk()
            ->assertJsonPath('data.totals.attempts', 1)
            ->assertJsonPath('data.totals.answered', 2)
            ->assertJsonPath('data.totals.correct', 1)
            ->assertJsonPath('data.totals.accuracy_percent', 50)
            ->assertJsonPath('data.by_subject.0.subject_title', 'Math')
            ->assertJsonPath('data.applications.approved', 1);
    }

    public function test_guest_analytics_is_empty_for_new_guest(): void
    {
        Sanctum::actingAs($this->guest);

        $this->getJson('/api/guest/analytics')
            ->assertOk()
            ->assertJsonPath('data.totals.attempts', 0)
            ->assertJsonPath('data.totals.accuracy_percent', null)
            ->assertJsonPath('data.by_date', []);
    }

    public function test_guest_can_fetch_and_update_profile(): void
    {
        Sanctum::actingAs($this->guest);

        $this->getJson('/api/guest/profile')
            ->assertOk()
            ->assertJsonPath('data.email', 'guest@example.com')
            ->assertJsonPath('data.role', 'guest');

        $this->patchJson('/api/guest/profile', ['name' => 'Rafi Khan', 'email' => 'Rafi.K@Example.com'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Rafi Khan')
            ->assertJsonPath('data.email', 'rafi.k@example.com')
            ->assertJsonPath('data.avatar_initials', 'RK')
            ->assertJsonPath('data.role', 'guest');

        // Email already used by someone else
        $this->patchJson('/api/guest/profile', ['email' => 'owner@example.com'])->assertStatus(422);
    }

    public function test_guest_password_change_needs_current_password(): void
    {
        Sanctum::actingAs($this->guest);

        $this->patchJson('/api/guest/profile', ['password' => 'new-guest-pass'])->assertStatus(422);
        $this->patchJson('/api/guest/profile', ['password' => 'new-guest-pass', 'current_password' => 'nope'])
            ->assertStatus(422)
            ->assertJsonPath('errors.current_password.0', 'Current password is incorrect.');

        $this->patchJson('/api/guest/profile', ['password' => 'new-guest-pass', 'current_password' => 'guest-pass-1'])
            ->assertOk();

        $this->postJson('/api/login', [
            'email' => 'guest@example.com', 'password' => 'new-guest-pass', 'portal' => 'guest',
        ])->assertOk();
    }
}

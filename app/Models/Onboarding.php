<?php

namespace App\Models;

use App\Concerns\LegacyQueries;
use Illuminate\Support\Facades\DB;

/** Ported from backend/Models/Onboarding.php — see User.php's docblock for the porting approach. */
class Onboarding
{
    use LegacyQueries;

    public function hiredApplicantsForEmployer(int $employerUserId): array
    {
        $applicants = $this->selectAll('SELECT app.application_id, app.applied_at, app.status AS application_status,
                   a.alumni_id, a.first_name, a.middle_name, a.last_name, a.birthdate, a.contact, a.gender,
                   a.civil_status, a.city, a.province, a.year_graduated, a.college, a.course,
                   u.email, u.secondary_email,
                   j.job_id, j.title,
                   e.company_name
            FROM applications app
            JOIN alumni a ON app.alumni_id = a.alumni_id
            JOIN user u ON a.user_id = u.user_id
            JOIN jobs j ON app.job_id = j.job_id
            LEFT JOIN employer e ON j.employer_id = e.user_id
            WHERE j.employer_id = ? AND app.status = \'Hired\'
            ORDER BY app.applied_at DESC', [$employerUserId]);

        foreach ($applicants as &$row) {
            $row['alumni_name'] = trim($row['first_name'].' '.$row['middle_name'].' '.$row['last_name']);
        }
        unset($row);

        if (empty($applicants)) {
            return [];
        }

        $alumniIds = array_values(array_unique(array_map(fn ($row) => (int) $row['alumni_id'], $applicants)));
        $placeholders = implode(',', array_fill(0, count($alumniIds), '?'));

        $pics = [];
        foreach ($this->selectAll("SELECT alumni_id, profile_pic FROM alumni WHERE alumni_id IN ($placeholders)", $alumniIds) as $row) {
            $pics[(int) $row['alumni_id']] = $row['profile_pic'];
        }

        foreach ($applicants as &$row) {
            $pic = $pics[(int) $row['alumni_id']] ?? null;
            $row['profile_image'] = $pic ? 'uploads/profile_picture/'.$pic : 'images/default-avatar.png';
        }

        return $applicants;
    }

    public function checklistsForEmployer(int $employerUserId): array
    {
        return $this->selectAll('SELECT id, title, description, is_custom, created_at FROM onboarding_checklist WHERE employer_id = ? ORDER BY created_at DESC', [$employerUserId]);
    }

    public function progressForEmployer(int $employerUserId): array
    {
        return $this->selectAll('SELECT ao.*, a.application_id, a.alumni_id
            FROM applicant_onboarding ao
            JOIN applications a ON ao.application_id = a.application_id
            JOIN jobs j ON a.job_id = j.job_id
            WHERE j.employer_id = ?', [$employerUserId]);
    }

    public function checklistItemsForEmployer(int $employerUserId): array
    {
        return $this->selectAll('SELECT oci.*, oc.title as checklist_title
            FROM onboarding_checklist_items oci
            JOIN onboarding_checklist oc ON oci.checklist_id = oc.id
            WHERE oc.employer_id = ?
            ORDER BY oc.title, oci.item_order', [$employerUserId]);
    }

    public function detailsById(int $onboardingId, int $employerId): ?array
    {
        if (!$this->onboardingBelongsToEmployer($onboardingId, $employerId)) {
            return null;
        }

        $details = $this->selectOne('SELECT ao.*, oc.title as checklist_name
            FROM applicant_onboarding ao
            LEFT JOIN onboarding_checklist oc ON ao.checklist_id = oc.id
            WHERE ao.id = ?', [$onboardingId]);

        if (!$details) {
            return null;
        }

        $details['checklist_items'] = $this->selectAll('SELECT oci.id, oci.item_text, oci.is_required, oci.item_order,
                   acp.is_completed, DATE(acp.completed_at) as completed_date, acp.notes
            FROM onboarding_checklist_items oci
            LEFT JOIN applicant_checklist_progress acp ON oci.id = acp.item_id AND acp.onboarding_id = ?
            WHERE oci.checklist_id = ?
            ORDER BY oci.item_order', [$onboardingId, $details['checklist_id']]);

        return $details;
    }

    public function saveChecklist(int $employerUserId, string $title, string $description, array $items): int
    {
        return DB::transaction(function () use ($employerUserId, $title, $description, $items) {
            $checklistId = $this->insertGetId('INSERT INTO onboarding_checklist (employer_id, title, description, is_custom) VALUES (?, ?, ?, TRUE)', [$employerUserId, $title, $description]);

            $order = 1;
            foreach ($items as $item) {
                if (empty($item['text'])) {
                    continue;
                }
                $isRequired = isset($item['is_required']) ? (int) $item['is_required'] : 0;
                $this->insert('INSERT INTO onboarding_checklist_items (checklist_id, item_text, is_required, item_order) VALUES (?, ?, ?, ?)', [$checklistId, $item['text'], $isRequired, $order]);
                ++$order;
            }

            return $checklistId;
        });
    }

    public function updateChecklist(int $checklistId, int $employerId, string $title, string $description, array $items): bool
    {
        if (!$this->checklistBelongsToEmployer($checklistId, $employerId)) {
            return false;
        }

        $onboardingIds = DB::transaction(function () use ($checklistId, $title, $description, $items) {
            $this->runUpdate('UPDATE onboarding_checklist SET title = ?, description = ?, updated_at = NOW() WHERE id = ?', [$title, $description, $checklistId]);

            $existingIds = array_column($this->selectAll('SELECT id FROM onboarding_checklist_items WHERE checklist_id = ?', [$checklistId]), 'id');
            $onboardingIds = array_column($this->selectAll('SELECT id FROM applicant_onboarding WHERE checklist_id = ?', [$checklistId]), 'id');

            $keptIds = [];
            $order = 1;
            foreach ($items as $item) {
                if (empty($item['text'])) {
                    continue;
                }
                $isRequired = isset($item['is_required']) ? (int) $item['is_required'] : 0;

                if (!empty($item['id']) && in_array((int) $item['id'], $existingIds, true)) {
                    $itemId = (int) $item['id'];
                    $this->runUpdate('UPDATE onboarding_checklist_items SET item_text = ?, is_required = ?, item_order = ? WHERE id = ?', [$item['text'], $isRequired, $order, $itemId]);
                    $keptIds[] = $itemId;
                } else {
                    $newItemId = $this->insertGetId('INSERT INTO onboarding_checklist_items (checklist_id, item_text, is_required, item_order) VALUES (?, ?, ?, ?)', [$checklistId, $item['text'], $isRequired, $order]);
                    $keptIds[] = $newItemId;

                    // Applicants already on this checklist need a progress row for the new item too.
                    foreach ($onboardingIds as $obId) {
                        $this->insert('INSERT INTO applicant_checklist_progress (onboarding_id, item_id) VALUES (?, ?)', [$obId, $newItemId]);
                    }
                }
                ++$order;
            }

            $removedIds = array_values(array_diff($existingIds, $keptIds));
            if (!empty($removedIds)) {
                $placeholders = implode(',', array_fill(0, count($removedIds), '?'));
                $this->runDelete("DELETE FROM applicant_checklist_progress WHERE item_id IN ($placeholders)", $removedIds);
                $this->runDelete("DELETE FROM onboarding_checklist_items WHERE id IN ($placeholders)", $removedIds);
            }

            return $onboardingIds;
        });

        // Item count may have changed, so every applicant on this checklist needs
        // their completion percentage/status recomputed.
        foreach ($onboardingIds as $obId) {
            $this->updateCompletionPercentage((int) $obId);
        }

        return true;
    }

    /** @return array{ok: bool, message: string} */
    public function deleteChecklist(int $checklistId, int $employerId): array
    {
        if (!$this->checklistBelongsToEmployer($checklistId, $employerId)) {
            return ['ok' => false, 'message' => 'Checklist not found'];
        }

        $inUse = (int) ($this->selectOne('SELECT COUNT(*) as total FROM applicant_onboarding WHERE checklist_id = ?', [$checklistId])['total'] ?? 0);

        if ($inUse > 0) {
            return ['ok' => false, 'message' => "Cannot delete: this checklist is currently assigned to {$inUse} applicant(s)."];
        }

        $ok = $this->runDelete('DELETE FROM onboarding_checklist WHERE id = ?', [$checklistId]) >= 0;

        return ['ok' => $ok, 'message' => $ok ? 'Checklist deleted' : 'Failed to delete checklist'];
    }

    public function itemsForChecklist(int $checklistId): array
    {
        return $this->selectAll('SELECT item_text, is_required FROM onboarding_checklist_items WHERE checklist_id = ? ORDER BY item_order', [$checklistId]);
    }

    public function checklistTitle(int $checklistId): ?string
    {
        return $this->selectOne('SELECT title FROM onboarding_checklist WHERE id = ?', [$checklistId])['title'] ?? null;
    }

    /** Alumni contact details for the applicant behind an application. */
    public function alumniContactForApplication(int $applicationId): ?array
    {
        return $this->selectOne('SELECT a.user_id, a.first_name, a.last_name, u.email, u.secondary_email
            FROM applications app
            JOIN alumni a ON app.alumni_id = a.alumni_id
            JOIN user u ON a.user_id = u.user_id
            WHERE app.application_id = ?', [$applicationId]);
    }

    public function applicationIdForOnboarding(int $onboardingId, int $employerId): ?int
    {
        if (!$this->onboardingBelongsToEmployer($onboardingId, $employerId)) {
            return null;
        }

        $row = $this->selectOne('SELECT application_id FROM applicant_onboarding WHERE id = ?', [$onboardingId]);

        return $row ? (int) $row['application_id'] : null;
    }

    public function updateChecklistItem(int $onboardingId, int $itemId, bool $isCompleted, int $employerId): bool
    {
        if (!$this->onboardingBelongsToEmployer($onboardingId, $employerId)) {
            return false;
        }

        $completedAt = $isCompleted ? date('Y-m-d H:i:s') : null;
        $existing = $this->selectOne('SELECT id FROM applicant_checklist_progress WHERE onboarding_id = ? AND item_id = ?', [$onboardingId, $itemId]);
        $completedInt = (int) $isCompleted;

        if ($existing) {
            $ok = $this->runUpdate('UPDATE applicant_checklist_progress SET is_completed = ?, completed_at = ? WHERE id = ?', [$completedInt, $completedAt, $existing['id']]) >= 0;
        } else {
            $ok = $this->insert('INSERT INTO applicant_checklist_progress (onboarding_id, item_id, is_completed, completed_at) VALUES (?, ?, ?, ?)', [$onboardingId, $itemId, $completedInt, $completedAt]);
        }

        if ($ok) {
            $this->updateCompletionPercentage($onboardingId);
        }

        return $ok;
    }

    private function updateCompletionPercentage(int $onboardingId): void
    {
        $total = (int) ($this->selectOne('SELECT COUNT(*) as total FROM onboarding_checklist_items oci
            JOIN applicant_onboarding ao ON oci.checklist_id = ao.checklist_id
            WHERE ao.id = ?', [$onboardingId])['total'] ?? 0);

        $completed = (int) ($this->selectOne('SELECT COUNT(*) as completed FROM applicant_checklist_progress WHERE onboarding_id = ? AND is_completed = 1', [$onboardingId])['completed'] ?? 0);

        $percentage = $total > 0 ? (int) round(($completed / $total) * 100) : 0;
        $status = 'pending';
        if ($percentage === 100) {
            $status = 'completed';
        } elseif ($percentage > 0) {
            $status = 'in_progress';
        }

        $this->runUpdate('UPDATE applicant_onboarding SET completion_percentage = ?, status = ?, updated_at = NOW() WHERE id = ?', [$percentage, $status, $onboardingId]);
    }

    public function assignChecklist(int $applicationId, int $checklistId, int $employerId): bool
    {
        if (!$this->applicationBelongsToEmployer($applicationId, $employerId) || !$this->checklistBelongsToEmployer($checklistId, $employerId)) {
            return false;
        }

        $existing = $this->selectOne('SELECT id FROM applicant_onboarding WHERE application_id = ?', [$applicationId]);

        if ($existing) {
            return $this->runUpdate("UPDATE applicant_onboarding SET checklist_id = ?, status = 'in_progress', updated_at = NOW() WHERE application_id = ?", [$checklistId, $applicationId]) >= 0;
        }

        $onboardingId = $this->insertGetId("INSERT INTO applicant_onboarding (application_id, checklist_id, status, started_at) VALUES (?, ?, 'in_progress', NOW())", [$applicationId, $checklistId]);

        $itemIds = array_column($this->selectAll('SELECT id FROM onboarding_checklist_items WHERE checklist_id = ?', [$checklistId]), 'id');

        foreach ($itemIds as $itemId) {
            $this->insert('INSERT INTO applicant_checklist_progress (onboarding_id, item_id) VALUES (?, ?)', [$onboardingId, $itemId]);
        }

        return true;
    }

    public function markComplete(int $onboardingId, int $employerId): bool
    {
        if (!$this->onboardingBelongsToEmployer($onboardingId, $employerId)) {
            return false;
        }

        return DB::transaction(function () use ($onboardingId) {
            $this->insert('INSERT INTO applicant_checklist_progress (onboarding_id, item_id, is_completed, completed_at)
                SELECT ?, oci.id, 1, NOW()
                FROM onboarding_checklist_items oci
                JOIN applicant_onboarding ao ON oci.checklist_id = ao.checklist_id
                WHERE ao.id = ?
                ON DUPLICATE KEY UPDATE is_completed = 1, completed_at = NOW()', [$onboardingId, $onboardingId]);

            return $this->runUpdate("UPDATE applicant_onboarding SET status = 'completed', completion_percentage = 100, completed_at = NOW(), updated_at = NOW() WHERE id = ?", [$onboardingId]) >= 0;
        });
    }

    public function saveNotes(int $onboardingId, string $notes, int $employerId): bool
    {
        if (!$this->onboardingBelongsToEmployer($onboardingId, $employerId)) {
            return false;
        }

        return $this->runUpdate('UPDATE applicant_onboarding SET notes = ?, updated_at = NOW() WHERE id = ?', [$notes, $onboardingId]) >= 0;
    }

    /** Application + job title + alumni name, used to compose the welcome email. */
    public function applicationSummary(int $applicationId, int $employerId): ?array
    {
        if (!$this->applicationBelongsToEmployer($applicationId, $employerId)) {
            return null;
        }

        return $this->selectOne('SELECT j.title, a.first_name, a.last_name
            FROM applications app
            JOIN jobs j ON app.job_id = j.job_id
            JOIN alumni a ON app.alumni_id = a.alumni_id
            WHERE app.application_id = ?', [$applicationId]);
    }

    public function recordWelcomeEmailSent(int $applicationId): void
    {
        $this->insert('INSERT INTO onboarding_emails (application_id, email_type, sent_at) VALUES (?, "welcome", NOW()) ON DUPLICATE KEY UPDATE sent_at = NOW()', [$applicationId]);
    }

    private function onboardingBelongsToEmployer(int $onboardingId, int $employerId): bool
    {
        return $this->selectOne('SELECT ao.id FROM applicant_onboarding ao
            JOIN applications a ON ao.application_id = a.application_id
            JOIN jobs j ON a.job_id = j.job_id
            WHERE ao.id = ? AND j.employer_id = ?', [$onboardingId, $employerId]) !== null;
    }

    private function applicationBelongsToEmployer(int $applicationId, int $employerId): bool
    {
        return $this->selectOne('SELECT app.application_id FROM applications app
            JOIN jobs j ON app.job_id = j.job_id
            WHERE app.application_id = ? AND j.employer_id = ?', [$applicationId, $employerId]) !== null;
    }

    private function checklistBelongsToEmployer(int $checklistId, int $employerId): bool
    {
        return $this->selectOne('SELECT id FROM onboarding_checklist WHERE id = ? AND employer_id = ?', [$checklistId, $employerId]) !== null;
    }
}

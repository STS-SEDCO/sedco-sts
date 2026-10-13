<?php
declare(strict_types=1);

/**
 * Regression test for end-to-end workflow privacy.
 * Runs without any live database, accounts, network access, or secrets.
 */
function normalized_role(?string $role): string
{
    return $role === 'head_of_division' ? 'head_of_department' : (string) $role;
}

function review_stage_for_role(?string $role): ?string
{
    return match (normalized_role($role)) {
        'training_section' => 'training',
        'head_of_department' => 'hod',
        'general_manager' => 'gm',
        'pengerusi_besar' => 'chairman',
        'finance' => 'finance',
        default => null,
    };
}

function user_can_review_applications(?array $user = null): bool
{
    return in_array(normalized_role($user['role'] ?? ''), [
        'admin', 'training_section', 'head_of_department', 'general_manager',
        'pengerusi_besar', 'finance',
    ], true);
}

require_once dirname(__DIR__) . '/includes/sts.php';

function check_permission(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAILED: " . $message . PHP_EOL);
        exit(1);
    }
}

$app = [
    'id' => 0,
    'user_id' => 100,
    'form_type' => 'BPL',
    'status' => 'pending',
    'current_stage' => 'hod',
    'department' => 'Finance Department',
    'assigned_hod_id' => 15,
];
$admin = ['id' => 1, 'role' => 'admin'];
$trainingA = ['id' => 2, 'role' => 'training_section'];
$trainingB = ['id' => 3, 'role' => 'training_section'];
$applicant = ['id' => 100, 'role' => 'staff'];
$stranger = ['id' => 101, 'role' => 'staff'];
$hod = ['id' => 15, 'role' => 'head_of_department', 'department' => 'Finance Department'];
$otherHod = ['id' => 16, 'role' => 'head_of_department', 'department' => 'Another Department'];
$gm = ['id' => 20, 'role' => 'general_manager'];
$chairman = ['id' => 30, 'role' => 'pengerusi_besar'];
$finance = ['id' => 40, 'role' => 'finance'];

foreach ([$admin, $trainingA, $trainingB, $applicant] as $viewer) {
    check_permission(sts_can_view_full_workflow($app, $viewer), 'Custodian and applicant have full workflow access');
    check_permission(sts_can_view_application($app, $viewer), 'Custodian and applicant can open application');
}
foreach ([$stranger, $hod, $otherHod, $gm, $chairman, $finance] as $viewer) {
    check_permission(!sts_can_view_full_workflow($app, $viewer), 'Other staff cannot inspect full workflow');
}
check_permission(!sts_can_view_application($app, $stranger), 'Unrelated staff cannot open application');
check_permission(sts_can_view_application($app, $hod), 'Assigned HOD may open HOD queue');
check_permission(!sts_can_view_application($app, $otherHod), 'Other HOD cannot open this department record');
check_permission(!sts_can_view_application($app, $gm), 'GM cannot open an application before GM stage');
check_permission(!sts_can_view_application($app, $finance), 'Finance cannot open an application before Finance stage');

$prior = $app;
$prior['current_stage'] = 'training';
check_permission(!sts_can_view_application($prior, $hod), 'Assigned HOD cannot open a record before HOD stage');

$otherStage = $app;
$otherStage['current_stage'] = 'gm';
check_permission(sts_can_view_application($otherStage, $gm), 'GM can open its active review stage');
check_permission(!sts_can_view_application($otherStage, $chairman), 'Pengerusi cannot open an application before Pengerusi stage');

$financeStage = $app;
$financeStage['current_stage'] = 'finance';
check_permission(sts_can_view_application($financeStage, $finance), 'Finance can open its active stage');

// Training officers can apply but cannot review their own BPL.
$trainingApplication = [
    'id' => 0,
    'user_id' => 2,
    'applicant_role' => 'training_section',
    'form_type' => 'BPL',
    'status' => 'pending',
    'current_stage' => 'training',
    'department' => 'Finance Department',
];
check_permission(!sts_can_review_application($trainingApplication, $trainingA), 'Training applicant cannot self-approve');
check_permission(sts_can_review_application($trainingApplication, $trainingB), 'Other Training officer may review');
check_permission(!sts_can_review_application($trainingApplication, $admin), 'Admin cannot bypass another Training reviewer');
check_permission(!sts_can_review_application($trainingApplication, $applicant), 'Staff cannot approve Training stage');

$adminApplication = $trainingApplication;
$adminApplication['user_id'] = 1;
$adminApplication['applicant_role'] = 'admin';
check_permission(!sts_can_review_application($adminApplication, $admin), 'Admin cannot self-approve');
check_permission(sts_can_review_application($adminApplication, $trainingB), 'Training may review Admin applicant');

$payload = [
    'nama' => 'Example Applicant',
    'tajuk' => 'Example Course',
    'ulasan_latihan' => 'Training private comment',
    'tt_latihan' => 'training signature',
    'ulasan_bahagian' => 'HOD private comment',
    'tt_bahagian' => 'HOD signature',
    'kelulusan_pgs' => 'Approved by GM',
    'tt_pgs' => 'GM signature',
    'tt_sedco' => 'Chairman signature',
    'bayaran_kursus' => '300',
    '_correction_fields' => ['tajuk'],
];

$hodPayload = sts_visible_application_payload($app, $hod, $payload);
check_permission(isset($hodPayload['nama'], $hodPayload['ulasan_bahagian']), 'HOD receives applicant and own fields');
check_permission(!isset($hodPayload['ulasan_latihan'], $hodPayload['tt_pgs'], $hodPayload['_correction_fields']), 'HOD cannot inspect other reviewers or correction metadata');

$gmPayload = sts_visible_application_payload($app, $gm, $payload);
check_permission(isset($gmPayload['kelulusan_pgs']), 'GM sees GM decision fields');
check_permission(!isset($gmPayload['tt_bahagian'], $gmPayload['tt_sedco']), 'GM does not see other signatures');

check_permission(sts_visible_application_payload($app, $trainingA, $payload) === $payload, 'Training officer has full form visibility');
check_permission(sts_visible_application_payload($app, $applicant, $payload) === $payload, 'Applicant has complete own form visibility');

$notBpl = $app;
$notBpl['form_type'] = 'PKK';
check_permission(!sts_can_view_application($notBpl, $hod), 'HOD cannot open another applicant\'s PKK through BPL review permission');

echo "All SEDCO workflow permissions tests passed." . PHP_EOL;

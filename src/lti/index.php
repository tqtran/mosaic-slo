<?php
declare(strict_types=1);

/**
 * LTI Integration Landing Page
 * 
 * Overview page for LTI (Learning Tools Interoperability) integration.
 * Provides access to test harness and documentation.
 */

require_once __DIR__ . '/../system/includes/init.php';

$pageTitle = 'LTI Integration';
$bodyClass = 'hold-transition sidebar-mini layout-fixed';
$currentPage = 'admin_lti';
$breadcrumbs = [
    ['url' => BASE_URL . 'administration/', 'label' => 'Home'],
    ['label' => 'LTI Integration']
];

require_once __DIR__ . '/../system/includes/header.php';
?>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">About LTI Integration</h3>
            </div>
            <div class="card-body">
                <p>
                    <strong>Learning Tools Interoperability (LTI)</strong> allows MOSAIC to integrate 
                    with Learning Management Systems like Canvas, Blackboard, and Moodle.
                </p>
                
                <h5 class="mt-4">How It Works</h5>
                <ol>
                    <li>Your LMS sends authenticated launch requests to MOSAIC</li>
                    <li>Instructors access assessment tools directly from course pages</li>
                    <li>Student and course data auto-syncs from the LMS</li>
                    <li>Assessment results can be sent back to the LMS gradebook</li>
                </ol>

                <h5 class="mt-4">Supported Versions</h5>
                <ul>
                    <li><strong>LTI 1.1:</strong> OAuth 1.0 signature validation</li>
                    <li><strong>LTI 1.3:</strong> JWT/JWKS public key verification (planned)</li>
                </ul>

                <h5 class="mt-4">Launch Endpoints</h5>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Tool</th>
                                                <th>URL</th>
                                                <th>Description</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td><strong>Launch Handler</strong></td>
                                                <td><code><?= BASE_URL ?>lti/launch.php</code></td>
                                                <td>Validates LTI POST and routes to assessment tool</td>
                                            </tr>
                                            <tr>
                                                <td><strong>Assessment Tool</strong></td>
                                                <td><code><?= BASE_URL ?>lti/assessment.php</code></td>
                                                <td>Instructor interface for entering student assessments</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <div class="alert alert-info mt-3">
                                    <i class="bi bi-info-circle me-2"></i>
                                    <strong>Configuration Required:</strong> Set your LTI consumer key/secret in 
                                    <a href="<?= BASE_URL ?>administration/institution.php">Institution settings</a> 
                                    before connecting to an LMS.
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <!-- Test Harness Card -->
                        <div class="card">
                            <div class="card-header bg-warning">
                                <h3 class="card-title">Development Tools</h3>
                            </div>
                            <div class="card-body">
                                <h5><i class="bi bi-hammer me-2"></i>LTI Test Harness</h5>
                                <p class="text-muted">
                                    Simulate LTI launches from a Learning Management System without 
                                    configuring an actual LMS integration.
                                </p>
                                <a href="<?= BASE_URL ?>lti/test.html" class="btn btn-warning w-100" target="_blank">
                                    <i class="bi bi-play-circle me-2"></i>Open Test Harness
                                </a>
                                <small class="text-muted d-block mt-2">
                                    Opens in new tab. Submit the form to test an LTI launch.
                                </small>
                            </div>
                        </div>

                        <!-- Quick Links Card -->
                        <div class="card mt-3">
                            <div class="card-header">
                                <h3 class="card-title">Resources</h3>
                            </div>
                            <div class="card-body">
                                <ul class="list-unstyled">
                                    <li class="mb-2">
                                        <i class="bi bi-book me-2"></i>
                                        <a href="https://www.imsglobal.org/activity/learning-tools-interoperability" target="_blank">
                                            IMS Global LTI Specs
                                        </a>
                                    </li>
                                    <li class="mb-2">
                                        <i class="bi bi-file-earmark-code me-2"></i>
                                        <a href="<?= BASE_URL ?>docs/implementation/SAMPLE_LTI_POST.txt" target="_blank">
                                            Sample LTI POST Data
                                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../system/includes/footer.php'; ?>

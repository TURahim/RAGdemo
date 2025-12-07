<?php

namespace Database\Seeders;

use BookStack\Entities\Models\Book;
use BookStack\Entities\Models\Chapter;
use BookStack\Entities\Models\Page;
use BookStack\Entities\Models\PageRevision;
use BookStack\Entities\Models\Bookshelf;
use BookStack\References\ReferenceStore;
use BookStack\Search\SearchIndex;
use BookStack\Users\Models\Role;
use BookStack\Users\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * MedTech SOP Seeder
 * 
 * Creates realistic SOP-style content for a medtech company including:
 * - Departments (Shelves): Quality Assurance, Manufacturing, Regulatory Affairs
 * - Knowledge Domains (Books): Document Control, Production Processes, etc.
 * - SOP Groups (Chapters): Categorized procedures
 * - SOP Documents (Pages): Detailed procedures with approval states
 */
class MedtechSOPSeeder extends Seeder
{
    protected array $users = [];
    protected SearchIndex $searchIndex;
    protected ReferenceStore $referenceStore;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->searchIndex = app(SearchIndex::class);
        $this->referenceStore = app(ReferenceStore::class);

        $this->command->info('Creating MedTech SOP seed data...');

        $departments = $this->getDepartmentStructure();

        // Clean up any existing medtech data to ensure idempotent runs
        if (Bookshelf::whereIn('name', array_column($departments, 'name'))->exists()) {
            $this->command->info('Existing MedTech SOP data found. Cleaning up before re-seeding...');
            $this->cleanupExistingData($departments);
        }

        // Create users
        $this->createUsers();

        // Create department structure
        $this->createDepartments($departments);

        $this->command->info('MedTech SOP seed data created successfully!');
        $this->command->info('Users created:');
        foreach ($this->users as $role => $user) {
            $this->command->info("  - {$user->name} ({$user->email}) - {$role}");
        }
    }

    /**
     * Create role-specific users for the medtech organization.
     */
    protected function createUsers(): void
    {
        $this->command->info('Creating users...');

        // QA Manager - Admin role (can approve)
        $this->users['qa_manager'] = $this->createUser(
            'Sarah Chen',
            'sarah.chen@medtech-demo.com',
            'admin'
        );

        // Production Supervisor - Editor role
        $this->users['production_supervisor'] = $this->createUser(
            'Marcus Thompson',
            'marcus.thompson@medtech-demo.com',
            'editor'
        );

        // Regulatory Specialist - Editor role
        $this->users['regulatory_specialist'] = $this->createUser(
            'Emily Rodriguez',
            'emily.rodriguez@medtech-demo.com',
            'editor'
        );

        // Quality Technician - Viewer role
        $this->users['quality_tech'] = $this->createUser(
            'James Wilson',
            'james.wilson@medtech-demo.com',
            'viewer'
        );
    }

    /**
     * Create a user with the specified role.
     */
    protected function createUser(string $name, string $email, string $roleName): User
    {
        $user = User::query()->where('email', $email)->first();
        $slugBase = Str::slug($name) ?: 'user';
        $slug = $slugBase;
        $suffix = 1;

        $slugExists = fn(string $candidate, ?int $ignoreId = null) => User::where('slug', $candidate)
            ->when($ignoreId, fn($q) => $q->where('id', '!=', $ignoreId))
            ->exists();

        while ($slugExists($slug, $user?->id)) {
            $slug = $slugBase . '-' . $suffix;
            $suffix++;
        }

        if (!$user) {
            $user = User::forceCreate([
                'name' => $name,
                'email' => $email,
                'slug' => $slug,
                'password' => Hash::make('password123'),
                'email_confirmed' => true,
                'external_auth_id' => '',
            ]);
        } else {
            // Ensure baseline flags are set for existing users
            $user->forceFill([
                'name' => $name,
                'slug' => $slug,
                'email_confirmed' => true,
            ])->save();
        }

        $role = Role::getRole($roleName);
        if ($role) {
            $hasRole = $user->roles()->where('id', $role->id)->exists();
            if (!$hasRole) {
                $user->attachRole($role);
            }
        }

        return $user;
    }

    /**
     * Create the department hierarchy.
     */
    protected function createDepartments(array $departments): void
    {
        $this->command->info('Creating departments...');

        foreach ($departments as $deptData) {
            $this->createDepartment($deptData);
        }
    }

    /**
     * Get the complete department structure definition.
     */
    protected function getDepartmentStructure(): array
    {
        return [
            [
                'name' => 'Quality Assurance Department',
                'description' => 'Quality management systems, document control, auditing, and compliance monitoring procedures for MedTech Solutions Inc.',
                'owner' => 'qa_manager',
                'books' => [
                    [
                        'name' => 'Document Control',
                        'description' => 'Procedures for managing controlled documents, including creation, review, approval, distribution, and archival processes.',
                        'chapters' => [
                            [
                                'name' => 'Document Creation',
                                'pages' => [
                                    $this->sopDocumentCreation(),
                                    $this->sopDocumentNumbering(),
                                    $this->sopTemplateManagement(),
                                ],
                            ],
                            [
                                'name' => 'Document Review & Approval',
                                'pages' => [
                                    $this->sopDocumentReview(),
                                    $this->sopChangeControl(),
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'Audit Management',
                        'description' => 'Internal and external audit procedures, CAPA management, and continuous improvement processes.',
                        'chapters' => [
                            [
                                'name' => 'Internal Audits',
                                'pages' => [
                                    $this->sopInternalAudit(),
                                    $this->sopAuditScheduling(),
                                ],
                            ],
                            [
                                'name' => 'CAPA',
                                'pages' => [
                                    $this->sopCapa(),
                                    $this->sopRootCauseAnalysis(),
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Manufacturing Operations',
                'description' => 'Production processes, equipment maintenance, and manufacturing quality control procedures.',
                'owner' => 'production_supervisor',
                'books' => [
                    [
                        'name' => 'Production Processes',
                        'description' => 'Standard operating procedures for all manufacturing processes and production line operations.',
                        'chapters' => [
                            [
                                'name' => 'Assembly Operations',
                                'pages' => [
                                    $this->sopDeviceAssembly(),
                                    $this->sopCleanroomProtocol(),
                                    $this->sopPackaging(),
                                ],
                            ],
                            [
                                'name' => 'Quality Control',
                                'pages' => [
                                    $this->sopInProcessInspection(),
                                    $this->sopFinalInspection(),
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'Equipment Maintenance',
                        'description' => 'Preventive and corrective maintenance procedures for production equipment.',
                        'chapters' => [
                            [
                                'name' => 'Preventive Maintenance',
                                'pages' => [
                                    $this->sopPreventiveMaintenance(),
                                    $this->sopCalibration(),
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Regulatory Affairs',
                'description' => 'Regulatory compliance, submission management, and post-market surveillance procedures.',
                'owner' => 'regulatory_specialist',
                'books' => [
                    [
                        'name' => 'Compliance Procedures',
                        'description' => 'Procedures ensuring compliance with FDA, ISO 13485, and other regulatory requirements.',
                        'chapters' => [
                            [
                                'name' => 'FDA Compliance',
                                'pages' => [
                                    $this->sopFdaCompliance(),
                                    $this->sopMdrReporting(),
                                ],
                            ],
                        ],
                    ],
                    [
                        'name' => 'Submission Guidelines',
                        'description' => 'Procedures for preparing and submitting regulatory filings.',
                        'chapters' => [
                            [
                                'name' => '510(k) Submissions',
                                'pages' => [
                                    $this->sop510kPreparation(),
                                    $this->sopTechnicalFile(),
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Create a department (shelf) with its books, chapters, and pages.
     */
    protected function createDepartment(array $deptData): void
    {
        $owner = $this->users[$deptData['owner']];
        $byData = [
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
            'owned_by' => $owner->id,
        ];

        // Create the shelf (department)
        $shelf = Bookshelf::create(array_merge([
            'name' => $deptData['name'],
            'slug' => Str::slug($deptData['name']),
            'description' => $deptData['description'],
        ], $byData));

        $this->searchIndex->indexEntity($shelf);

        $books = [];
        foreach ($deptData['books'] as $bookData) {
            $book = $this->createBook($bookData, $owner);
            $books[] = $book;
        }

        // Attach books to shelf
        $shelf->books()->attach($books);

        $this->command->info("  Created department: {$deptData['name']}");
    }

    /**
     * Create a book (knowledge domain) with its chapters and pages.
     */
    protected function createBook(array $bookData, User $owner): Book
    {
        $byData = [
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
            'owned_by' => $owner->id,
        ];

        $book = Book::create(array_merge([
            'name' => $bookData['name'],
            'slug' => Str::slug($bookData['name']),
            'description' => $bookData['description'],
        ], $byData));

        $this->searchIndex->indexEntity($book);

        foreach ($bookData['chapters'] as $chapterData) {
            $this->createChapter($chapterData, $book, $owner);
        }

        return $book;
    }

    /**
     * Create a chapter (SOP group) with its pages.
     */
    protected function createChapter(array $chapterData, Book $book, User $owner): Chapter
    {
        $byData = [
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
            'owned_by' => $owner->id,
        ];

        $chapter = Chapter::create(array_merge([
            'name' => $chapterData['name'],
            'slug' => Str::slug($chapterData['name']),
            'book_id' => $book->id,
            'description' => "Standard operating procedures for {$chapterData['name']}",
        ], $byData));

        $this->searchIndex->indexEntity($chapter);

        foreach ($chapterData['pages'] as $pageData) {
            $this->createPage($pageData, $book, $chapter, $owner);
        }

        return $chapter;
    }

    /**
     * Remove existing MedTech seed data for a clean re-run.
     */
    protected function cleanupExistingData(array $departments): void
    {
        $deptNames = array_column($departments, 'name');
        $bookNames = [];
        $chapterNames = [];
        $pageNames = [];

        foreach ($departments as $dept) {
            foreach ($dept['books'] as $book) {
                $bookNames[] = $book['name'];
                foreach ($book['chapters'] as $chapter) {
                    $chapterNames[] = $chapter['name'];
                    foreach ($chapter['pages'] as $page) {
                        $pageNames[] = $page['name'];
                    }
                }
            }
        }

        Page::query()->whereIn('name', $pageNames)->get()->each->delete();
        Chapter::query()->whereIn('name', $chapterNames)->get()->each->delete();
        Book::query()->whereIn('name', $bookNames)->get()->each->delete();
        Bookshelf::query()->whereIn('name', $deptNames)->get()->each->delete();
    }

    /**
     * Create a page (SOP document) with approval state.
     */
    protected function createPage(array $pageData, Book $book, Chapter $chapter, User $owner): Page
    {
        $byData = [
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
            'owned_by' => $owner->id,
        ];

        $html = $pageData['html'];
        $text = strip_tags($html);

        $page = Page::create(array_merge([
            'name' => $pageData['name'],
            'slug' => Str::slug($pageData['name']),
            'book_id' => $book->id,
            'chapter_id' => $chapter->id,
            'html' => $html,
            'text' => $text,
            'revision_count' => 1,
            'editor' => 'wysiwyg',
            'draft' => false,
            'template' => false,
        ], $byData));

        // Handle entity_page_data
        $page->relatedData()->updateOrCreate(
            ['page_id' => $page->id],
            []
        );

        $this->searchIndex->indexEntity($page);
        $this->referenceStore->updateForEntity($page);

        // Create initial revision with approval state
        $this->createRevisionWithApprovalState($page, $pageData, $owner);

        return $page;
    }

    /**
     * Create a revision with the specified approval state.
     */
    protected function createRevisionWithApprovalState(Page $page, array $pageData, User $owner): void
    {
        $approvalState = $pageData['approval_state'] ?? 'approved';
        $reviewIntervalDays = $pageData['review_interval_days'] ?? 365;

        // Create the revision
        $revision = PageRevision::create([
            'page_id' => $page->id,
            'name' => $page->name,
            'slug' => $page->slug,
            'book_slug' => $page->book->slug,
            'html' => $page->html,
            'text' => $page->text,
            'type' => 'version',
            'revision_number' => 1,
            'summary' => 'Initial SOP creation',
            'created_by' => $owner->id,
            'created_at' => now()->subDays(rand(30, 180)),
            'updated_at' => now()->subDays(rand(1, 30)),
            'status' => $this->mapApprovalState($approvalState),
        ]);

        // Handle approval-specific data
        if ($approvalState === 'approved') {
            $approver = $this->users['qa_manager'];
            $approvedAt = now()->subDays(rand(7, 60));
            
            $revision->update([
                'approved_by' => $approver->id,
                'approved_at' => $approvedAt,
                'review_notes' => 'Approved. Document meets quality standards.',
            ]);

            // Calculate next review date
            $nextReviewDate = $this->calculateNextReviewDate($approvalState, $reviewIntervalDays);

            // Update page with approved revision info
            $page->relatedData()->update([
                'approved_revision_id' => $revision->id,
                'next_review_date' => $nextReviewDate?->toDateString(),
                'review_interval_days' => $reviewIntervalDays,
            ]);
        } elseif ($approvalState === 'overdue') {
            // Overdue pages are approved but past their review date
            $approver = $this->users['qa_manager'];
            $approvedAt = now()->subDays(rand(400, 500));
            
            $revision->update([
                'status' => PageRevision::STATUS_APPROVED,
                'approved_by' => $approver->id,
                'approved_at' => $approvedAt,
                'review_notes' => 'Approved. Document meets quality standards.',
            ]);

            // Set review date in the past
            $overdueBy = rand(1, 30);
            $nextReviewDate = now()->subDays($overdueBy);

            $page->relatedData()->update([
                'approved_revision_id' => $revision->id,
                'next_review_date' => $nextReviewDate->toDateString(),
                'review_interval_days' => $reviewIntervalDays,
            ]);
        } elseif ($approvalState === 'in_review') {
            $revision->update([
                'review_notes' => null,
            ]);
        } elseif ($approvalState === 'rejected') {
            $revision->update([
                'approved_by' => $this->users['qa_manager']->id,
                'review_notes' => 'Rejected. Please address the following: Procedure steps need more detail. Add equipment calibration requirements.',
            ]);
        }
    }

    /**
     * Map approval state string to PageRevision constant.
     */
    protected function mapApprovalState(string $state): string
    {
        return match ($state) {
            'approved', 'overdue' => PageRevision::STATUS_APPROVED,
            'in_review' => PageRevision::STATUS_IN_REVIEW,
            'rejected' => PageRevision::STATUS_REJECTED,
            default => PageRevision::STATUS_DRAFT,
        };
    }

    /**
     * Calculate the next review date based on approval state.
     */
    protected function calculateNextReviewDate(string $state, int $intervalDays): ?Carbon
    {
        if ($state === 'approved') {
            // Random future date within the interval
            return now()->addDays(rand(30, $intervalDays));
        }
        return null;
    }

    // ========================================
    // SOP Content Templates
    // ========================================

    protected function sopDocumentCreation(): array
    {
        return [
            'name' => 'SOP-QA-001: Document Creation Procedure',
            'approval_state' => 'approved',
            'review_interval_days' => 365,
            'html' => $this->buildSopHtml(
                'SOP-QA-001',
                'Document Creation Procedure',
                'This procedure establishes the requirements for creating new controlled documents within the Quality Management System.',
                'All employees involved in document creation and the Document Control team.',
                [
                    'Log into the Document Management System (DMS).',
                    'Select "Create New Document" from the main menu.',
                    'Choose the appropriate document template based on document type.',
                    'Complete all required metadata fields including document title, department, and classification.',
                    'Draft the document content following the template structure.',
                    'Save the draft and initiate the review workflow.',
                    'Document Control assigns a unique document number per SOP-QA-002.',
                ],
                ['Document Request Form (QA-F-001)', 'Document Control Log (QA-F-002)']
            ),
        ];
    }

    protected function sopDocumentNumbering(): array
    {
        return [
            'name' => 'SOP-QA-002: Document Numbering System',
            'approval_state' => 'approved',
            'review_interval_days' => 730,
            'html' => $this->buildSopHtml(
                'SOP-QA-002',
                'Document Numbering System',
                'This procedure defines the document numbering convention used for all controlled documents.',
                'Document Control personnel.',
                [
                    'Document numbers follow the format: [TYPE]-[DEPT]-[SEQ]',
                    'TYPE codes: SOP (Standard Operating Procedure), WI (Work Instruction), F (Form), POL (Policy)',
                    'DEPT codes: QA (Quality Assurance), MFG (Manufacturing), REG (Regulatory), ENG (Engineering)',
                    'SEQ: Three-digit sequential number starting at 001',
                    'Assign the next available number in the Document Control Log.',
                    'Version numbers use format: Rev [Letter] (e.g., Rev A, Rev B)',
                ],
                ['Document Control Log (QA-F-002)', 'Document Number Request (QA-F-003)']
            ),
        ];
    }

    protected function sopTemplateManagement(): array
    {
        return [
            'name' => 'SOP-QA-003: Template Management',
            'approval_state' => 'in_review',
            'review_interval_days' => 365,
            'html' => $this->buildSopHtml(
                'SOP-QA-003',
                'Template Management',
                'This procedure establishes requirements for creating, approving, and maintaining document templates.',
                'Document Control Manager and Quality Assurance team.',
                [
                    'New template requests must be submitted via Template Request Form.',
                    'Document Control reviews request for necessity and compliance.',
                    'Draft template following corporate formatting standards.',
                    'Include all required sections: header, revision history, approval signatures.',
                    'Submit template for review by affected departments.',
                    'Upon approval, publish template to DMS template library.',
                    'Obsolete templates must be archived, not deleted.',
                ],
                ['Template Request Form (QA-F-010)', 'Template Approval Record (QA-F-011)']
            ),
        ];
    }

    protected function sopDocumentReview(): array
    {
        return [
            'name' => 'SOP-QA-004: Document Review and Approval',
            'approval_state' => 'approved',
            'review_interval_days' => 365,
            'html' => $this->buildSopHtml(
                'SOP-QA-004',
                'Document Review and Approval',
                'This procedure defines the review and approval process for all controlled documents.',
                'All document authors, reviewers, and approvers.',
                [
                    'Author completes document draft and submits for review.',
                    'System automatically routes document to designated reviewers based on document type.',
                    'Reviewers have 5 business days to complete review.',
                    'Reviewers must verify technical accuracy, regulatory compliance, and formatting.',
                    'All review comments must be addressed and documented.',
                    'Final approval requires signature from Department Manager and QA Representative.',
                    'Approved documents are published and distributed per SOP-QA-005.',
                ],
                ['Document Review Checklist (QA-F-004)', 'Approval Signature Log (QA-F-005)']
            ),
        ];
    }

    protected function sopChangeControl(): array
    {
        return [
            'name' => 'SOP-QA-005: Change Control Procedure',
            'approval_state' => 'approved',
            'review_interval_days' => 365,
            'html' => $this->buildSopHtml(
                'SOP-QA-005',
                'Change Control Procedure',
                'This procedure establishes the process for controlling changes to documents, processes, and products.',
                'All personnel initiating or affected by changes.',
                [
                    'Complete Change Request Form identifying the proposed change.',
                    'Categorize change as Administrative, Minor, or Major.',
                    'Administrative changes: typos, formatting (Document Control approval only).',
                    'Minor changes: clarifications, minor process updates (Department Head approval).',
                    'Major changes: significant process changes, regulatory impact (Change Control Board approval).',
                    'Implement approved changes and update affected documents.',
                    'Provide training on changes to affected personnel.',
                    'Close change request with effectiveness verification.',
                ],
                ['Change Request Form (QA-F-020)', 'Change Impact Assessment (QA-F-021)']
            ),
        ];
    }

    protected function sopInternalAudit(): array
    {
        return [
            'name' => 'SOP-QA-010: Internal Audit Procedure',
            'approval_state' => 'approved',
            'review_interval_days' => 365,
            'html' => $this->buildSopHtml(
                'SOP-QA-010',
                'Internal Audit Procedure',
                'This procedure defines the process for conducting internal quality management system audits.',
                'Internal auditors, auditees, and Quality Assurance management.',
                [
                    'Annual audit schedule is prepared by QA Manager based on risk assessment.',
                    'Audit team is assigned ensuring auditor independence from area being audited.',
                    'Prepare audit plan and checklist based on applicable standards (ISO 13485, FDA 21 CFR 820).',
                    'Conduct opening meeting with auditee to explain audit scope and process.',
                    'Perform audit using objective evidence: documents, records, interviews, observations.',
                    'Document findings as Observations, Minor Nonconformances, or Major Nonconformances.',
                    'Conduct closing meeting to present preliminary findings.',
                    'Issue formal audit report within 5 business days.',
                    'Auditee responds with CAPA for all nonconformances per SOP-QA-012.',
                ],
                ['Audit Schedule (QA-F-030)', 'Audit Checklist (QA-F-031)', 'Audit Report Template (QA-F-032)']
            ),
        ];
    }

    protected function sopAuditScheduling(): array
    {
        return [
            'name' => 'SOP-QA-011: Audit Schedule Management',
            'approval_state' => 'draft',
            'review_interval_days' => 365,
            'html' => $this->buildSopHtml(
                'SOP-QA-011',
                'Audit Schedule Management',
                'This procedure defines how the annual internal audit schedule is created and maintained.',
                'QA Manager and Management Representative.',
                [
                    'Review previous year audit results and findings.',
                    'Assess risk levels of each QMS process area.',
                    'High-risk areas: audit at least twice annually.',
                    'Medium-risk areas: audit at least annually.',
                    'Low-risk areas: audit at least every 18 months.',
                    'Consider regulatory requirements and customer audit schedules.',
                    'Draft schedule and obtain Management Representative approval.',
                    'Publish schedule and communicate to all department managers.',
                    'Review and update schedule quarterly or as needed.',
                ],
                ['Risk Assessment Matrix (QA-F-033)', 'Annual Audit Schedule (QA-F-034)']
            ),
        ];
    }

    protected function sopCapa(): array
    {
        return [
            'name' => 'SOP-QA-012: CAPA Management',
            'approval_state' => 'approved',
            'review_interval_days' => 365,
            'html' => $this->buildSopHtml(
                'SOP-QA-012',
                'Corrective and Preventive Action (CAPA) Management',
                'This procedure establishes the system for identifying, documenting, and resolving quality issues through corrective and preventive actions.',
                'All employees who identify quality issues; CAPA owners; QA team.',
                [
                    'Any employee may initiate a CAPA by completing the CAPA Request Form.',
                    'QA reviews and assigns CAPA number, priority, and owner within 2 business days.',
                    'CAPA owner performs root cause analysis using appropriate tools (5 Why, Fishbone, etc.).',
                    'Define corrective actions to eliminate the root cause.',
                    'Define preventive actions to prevent recurrence.',
                    'Establish implementation timeline based on CAPA priority.',
                    'Implement actions and document completion evidence.',
                    'QA verifies effectiveness 30-90 days after implementation.',
                    'Close CAPA upon successful effectiveness verification.',
                ],
                ['CAPA Request Form (QA-F-040)', 'Root Cause Analysis Template (QA-F-041)', 'CAPA Log (QA-F-042)']
            ),
        ];
    }

    protected function sopRootCauseAnalysis(): array
    {
        return [
            'name' => 'SOP-QA-013: Root Cause Analysis Methods',
            'approval_state' => 'overdue',
            'review_interval_days' => 365,
            'html' => $this->buildSopHtml(
                'SOP-QA-013',
                'Root Cause Analysis Methods',
                'This procedure provides guidance on selecting and applying root cause analysis tools.',
                'CAPA owners, Quality Engineers, Process Engineers.',
                [
                    'Select appropriate RCA tool based on problem complexity.',
                    '5 Why Analysis: Use for straightforward problems with linear cause chains.',
                    'Fishbone (Ishikawa) Diagram: Use for complex problems with multiple potential causes.',
                    'Fault Tree Analysis: Use for safety-critical issues requiring systematic evaluation.',
                    'Document all analysis steps and rationale for conclusions.',
                    'Identify systemic issues vs. isolated incidents.',
                    'Validate root cause before defining corrective actions.',
                    'Attach completed RCA documentation to CAPA record.',
                ],
                ['5 Why Template (QA-F-043)', 'Fishbone Diagram Template (QA-F-044)', 'Fault Tree Template (QA-F-045)']
            ),
        ];
    }

    protected function sopDeviceAssembly(): array
    {
        return [
            'name' => 'SOP-MFG-001: Medical Device Assembly',
            'approval_state' => 'approved',
            'review_interval_days' => 365,
            'html' => $this->buildSopHtml(
                'SOP-MFG-001',
                'Medical Device Assembly Procedure',
                'This procedure defines the assembly process for the CardioMonitor Pro medical device.',
                'Production Technicians, Production Supervisors, Quality Inspectors.',
                [
                    'Verify all required components are available per Bill of Materials.',
                    'Confirm cleanroom environment meets Class 7 requirements.',
                    'Don appropriate cleanroom attire per SOP-MFG-003.',
                    'Verify workstation is clean and ESD precautions are in place.',
                    'Follow Device History Record (DHR) assembly sequence.',
                    'Torque all fasteners to specification using calibrated tools.',
                    'Perform in-process inspections at designated checkpoints.',
                    'Complete all DHR entries with operator initials and timestamps.',
                    'Transfer completed assembly to QC inspection area.',
                ],
                ['Device History Record (MFG-F-001)', 'Assembly Checklist (MFG-F-002)']
            ),
        ];
    }

    protected function sopCleanroomProtocol(): array
    {
        return [
            'name' => 'SOP-MFG-002: Cleanroom Entry and Exit',
            'approval_state' => 'approved',
            'review_interval_days' => 365,
            'html' => $this->buildSopHtml(
                'SOP-MFG-002',
                'Cleanroom Entry and Exit Protocol',
                'This procedure defines requirements for entering and exiting the ISO Class 7 cleanroom.',
                'All personnel requiring cleanroom access.',
                [
                    'Remove all jewelry, watches, and personal items before gowning.',
                    'Wash hands thoroughly with approved cleanroom soap.',
                    'Enter gowning room and don shoe covers first.',
                    'Put on cleanroom smock, ensuring full coverage.',
                    'Don hood, tucking edges under smock collar.',
                    'Put on cleanroom gloves over smock cuffs.',
                    'Pass through air shower for minimum 30 seconds.',
                    'Enter cleanroom through interlocked door system.',
                    'Exit procedure: reverse order, dispose of single-use items properly.',
                ],
                ['Gowning Qualification Record (MFG-F-010)', 'Cleanroom Access Log (MFG-F-011)']
            ),
        ];
    }

    protected function sopPackaging(): array
    {
        return [
            'name' => 'SOP-MFG-003: Product Packaging and Labeling',
            'approval_state' => 'in_review',
            'review_interval_days' => 365,
            'html' => $this->buildSopHtml(
                'SOP-MFG-003',
                'Product Packaging and Labeling Procedure',
                'This procedure defines the packaging and labeling requirements for finished medical devices.',
                'Packaging Technicians, Quality Inspectors.',
                [
                    'Verify product has passed final inspection and is released for packaging.',
                    'Retrieve correct packaging materials per BOM using FIFO.',
                    'Verify label information matches DHR and product configuration.',
                    'Apply UDI label to primary package as per FDA requirements.',
                    'Include Instructions for Use (IFU) document.',
                    'Seal package using validated sealing parameters.',
                    'Perform package integrity verification.',
                    'Apply lot/serial number to outer carton.',
                    'Transfer to finished goods inventory.',
                ],
                ['Packaging Specification (MFG-F-020)', 'Label Verification Checklist (MFG-F-021)']
            ),
        ];
    }

    protected function sopInProcessInspection(): array
    {
        return [
            'name' => 'SOP-MFG-004: In-Process Inspection',
            'approval_state' => 'approved',
            'review_interval_days' => 365,
            'html' => $this->buildSopHtml(
                'SOP-MFG-004',
                'In-Process Inspection Procedure',
                'This procedure defines inspection requirements during the manufacturing process.',
                'Quality Inspectors, Production Technicians.',
                [
                    'Perform inspections at designated DHR inspection points.',
                    'Verify operator has completed all previous process steps.',
                    'Check critical dimensions using calibrated measurement equipment.',
                    'Verify solder joint quality using approved workmanship standards.',
                    'Perform functional test per test protocol.',
                    'Document all inspection results in DHR.',
                    'Apply inspection stamp or label upon acceptance.',
                    'Segregate and document any nonconforming material per SOP-QA-020.',
                ],
                ['In-Process Inspection Checklist (MFG-F-030)', 'Nonconformance Report (QA-F-050)']
            ),
        ];
    }

    protected function sopFinalInspection(): array
    {
        return [
            'name' => 'SOP-MFG-005: Final Product Inspection',
            'approval_state' => 'rejected',
            'review_interval_days' => 365,
            'html' => $this->buildSopHtml(
                'SOP-MFG-005',
                'Final Product Inspection Procedure',
                'This procedure defines the final inspection and release requirements for finished devices.',
                'Quality Engineers, QA Manager.',
                [
                    'Verify DHR is complete with all required signatures.',
                    'Review all in-process inspection records for conformance.',
                    'Perform final functional testing per approved test protocol.',
                    'Conduct cosmetic inspection against visual standards.',
                    'Verify labeling accuracy and completeness.',
                    'Review any nonconformances and verify dispositions.',
                    'Complete Final Inspection Report.',
                    'Authorize product release to finished goods.',
                ],
                ['Final Inspection Report (MFG-F-040)', 'Product Release Authorization (MFG-F-041)']
            ),
        ];
    }

    protected function sopPreventiveMaintenance(): array
    {
        return [
            'name' => 'SOP-MFG-010: Preventive Maintenance Program',
            'approval_state' => 'approved',
            'review_interval_days' => 365,
            'html' => $this->buildSopHtml(
                'SOP-MFG-010',
                'Preventive Maintenance Program',
                'This procedure establishes the preventive maintenance program for production equipment.',
                'Maintenance Technicians, Production Supervisors, Engineering.',
                [
                    'All production equipment is assigned a unique equipment ID.',
                    'PM schedules are established based on manufacturer recommendations and equipment criticality.',
                    'PM tasks are scheduled in the Computerized Maintenance Management System (CMMS).',
                    'Maintenance Technician receives work order and retrieves PM procedure.',
                    'Perform all PM tasks as specified in equipment-specific procedure.',
                    'Document all findings, measurements, and actions taken.',
                    'Replace worn components per manufacturer specifications.',
                    'Return equipment to production and verify functionality.',
                    'Close work order in CMMS with completion details.',
                ],
                ['PM Schedule (MFG-F-050)', 'Maintenance Work Order (MFG-F-051)']
            ),
        ];
    }

    protected function sopCalibration(): array
    {
        return [
            'name' => 'SOP-MFG-011: Equipment Calibration',
            'approval_state' => 'approved',
            'review_interval_days' => 365,
            'html' => $this->buildSopHtml(
                'SOP-MFG-011',
                'Equipment Calibration Procedure',
                'This procedure defines the calibration requirements for measurement and test equipment.',
                'Calibration Technicians, Quality Engineers.',
                [
                    'All measurement equipment affecting product quality requires calibration.',
                    'Calibration intervals are established based on equipment type and usage.',
                    'Calibration is traceable to NIST or equivalent national standards.',
                    'Remove equipment from service when calibration is due.',
                    'Perform calibration per equipment-specific calibration procedure.',
                    'Document all calibration data and as-found/as-left conditions.',
                    'Apply calibration label showing due date and technician ID.',
                    'Evaluate impact of out-of-tolerance conditions on previously produced product.',
                    'Maintain calibration records for equipment lifetime plus 3 years.',
                ],
                ['Calibration Schedule (MFG-F-060)', 'Calibration Record (MFG-F-061)', 'OOT Investigation Form (MFG-F-062)']
            ),
        ];
    }

    protected function sopFdaCompliance(): array
    {
        return [
            'name' => 'SOP-REG-001: FDA QSR Compliance',
            'approval_state' => 'approved',
            'review_interval_days' => 365,
            'html' => $this->buildSopHtml(
                'SOP-REG-001',
                'FDA Quality System Regulation Compliance',
                'This procedure ensures ongoing compliance with FDA 21 CFR Part 820 Quality System Regulation.',
                'Regulatory Affairs, Quality Assurance, All Department Managers.',
                [
                    'Regulatory Affairs maintains current knowledge of QSR requirements.',
                    'Annual gap assessment is performed against QSR requirements.',
                    'All QMS procedures are reviewed for QSR compliance before approval.',
                    'Design control requirements (Subpart C) are implemented per SOP-ENG-001.',
                    'Production and process controls (Subpart G) per manufacturing SOPs.',
                    'CAPA requirements (Subpart J) per SOP-QA-012.',
                    'Document control requirements (Subpart D) per SOP-QA-001 through QA-005.',
                    'Management review includes QSR compliance status.',
                    'FDA inspection readiness is maintained at all times.',
                ],
                ['QSR Gap Assessment (REG-F-001)', 'Compliance Matrix (REG-F-002)']
            ),
        ];
    }

    protected function sopMdrReporting(): array
    {
        return [
            'name' => 'SOP-REG-002: Medical Device Reporting (MDR)',
            'approval_state' => 'approved',
            'review_interval_days' => 365,
            'html' => $this->buildSopHtml(
                'SOP-REG-002',
                'Medical Device Reporting (MDR) Procedure',
                'This procedure defines the process for reporting adverse events to FDA as required by 21 CFR Part 803.',
                'Regulatory Affairs, Quality Assurance, Customer Service.',
                [
                    'All complaints are evaluated for MDR reportability.',
                    'Events involving death or serious injury require 30-day report.',
                    'Malfunctions likely to cause death or serious injury require 30-day report.',
                    'Regulatory Affairs makes final reportability determination within 5 days.',
                    'If reportable, complete FDA Form 3500A.',
                    'Submit report via FDA eMDR system within required timeframe.',
                    'Maintain records of all reportability decisions.',
                    'Conduct trend analysis of complaints for potential reporting.',
                    'Annual certification letter submitted to FDA.',
                ],
                ['MDR Decision Tree (REG-F-010)', 'MDR Report Log (REG-F-011)']
            ),
        ];
    }

    protected function sop510kPreparation(): array
    {
        return [
            'name' => 'SOP-REG-010: 510(k) Submission Preparation',
            'approval_state' => 'approved',
            'review_interval_days' => 730,
            'html' => $this->buildSopHtml(
                'SOP-REG-010',
                '510(k) Premarket Notification Preparation',
                'This procedure defines the process for preparing 510(k) premarket notification submissions.',
                'Regulatory Affairs, R&D Engineering, Quality Assurance.',
                [
                    'Initiate 510(k) project with regulatory strategy meeting.',
                    'Identify predicate device(s) and document selection rationale.',
                    'Prepare device description and intended use statement.',
                    'Conduct substantial equivalence comparison.',
                    'Compile performance testing data (biocompatibility, electrical safety, EMC).',
                    'Prepare software documentation per FDA software guidance.',
                    'Compile labeling package (IFU, labels, promotional materials).',
                    'Prepare 510(k) summary or statement.',
                    'Conduct internal review before submission.',
                    'Submit via FDA eSTAR system.',
                    'Track and respond to any FDA Additional Information requests.',
                ],
                ['510(k) Checklist (REG-F-020)', 'Predicate Comparison Table (REG-F-021)', 'eSTAR Template (REG-F-022)']
            ),
        ];
    }

    protected function sopTechnicalFile(): array
    {
        return [
            'name' => 'SOP-REG-011: Technical File Management',
            'approval_state' => 'in_review',
            'review_interval_days' => 365,
            'html' => $this->buildSopHtml(
                'SOP-REG-011',
                'Technical File Management',
                'This procedure defines requirements for creating and maintaining Technical Files for EU MDR compliance.',
                'Regulatory Affairs, Quality Assurance, Engineering.',
                [
                    'Technical File is required for all devices marketed in EU under MDR.',
                    'Structure follows Annex II/III requirements based on device classification.',
                    'Section 1: Device description and specifications.',
                    'Section 2: Manufacturing information including suppliers.',
                    'Section 3: Design and manufacturing information.',
                    'Section 4: General safety and performance requirements (GSPR).',
                    'Section 5: Benefit-risk analysis and risk management.',
                    'Section 6: Product verification and validation.',
                    'Section 7: Clinical evaluation per MDR Article 61.',
                    'Maintain Technical File current with all design changes.',
                    'Make available for Notified Body review upon request.',
                ],
                ['Technical File Index (REG-F-030)', 'GSPR Checklist (REG-F-031)', 'Clinical Evaluation Report Template (REG-F-032)']
            ),
        ];
    }

    /**
     * Build standardized SOP HTML content.
     */
    protected function buildSopHtml(
        string $sopNumber,
        string $title,
        string $purpose,
        string $scope,
        array $procedures,
        array $records
    ): string {
        $procedureSteps = '';
        foreach ($procedures as $index => $step) {
            $stepNum = $index + 1;
            $procedureSteps .= "<li><strong>{$stepNum}.</strong> {$step}</li>\n";
        }

        $recordsList = implode(', ', $records);

        return <<<HTML
<h2>1. PURPOSE</h2>
<p>{$purpose}</p>

<h2>2. SCOPE</h2>
<p>{$scope}</p>

<h2>3. RESPONSIBILITIES</h2>
<table border="1" cellpadding="8" cellspacing="0" style="border-collapse: collapse; width: 100%;">
    <thead>
        <tr style="background-color: #f0f0f0;">
            <th>Role</th>
            <th>Responsibility</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>Document Owner</td>
            <td>Maintains this procedure and ensures accuracy</td>
        </tr>
        <tr>
            <td>Quality Assurance</td>
            <td>Reviews and approves procedure changes</td>
        </tr>
        <tr>
            <td>All Personnel</td>
            <td>Follows this procedure as applicable to their role</td>
        </tr>
    </tbody>
</table>

<h2>4. DEFINITIONS</h2>
<ul>
    <li><strong>SOP:</strong> Standard Operating Procedure</li>
    <li><strong>QMS:</strong> Quality Management System</li>
    <li><strong>CAPA:</strong> Corrective and Preventive Action</li>
</ul>

<h2>5. PROCEDURE</h2>
<ol>
{$procedureSteps}
</ol>

<h2>6. QUALITY RECORDS</h2>
<p>The following quality records are generated by this procedure:</p>
<p><em>{$recordsList}</em></p>

<h2>7. REVISION HISTORY</h2>
<table border="1" cellpadding="8" cellspacing="0" style="border-collapse: collapse; width: 100%;">
    <thead>
        <tr style="background-color: #f0f0f0;">
            <th>Rev</th>
            <th>Date</th>
            <th>Description</th>
            <th>Author</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>A</td>
            <td>Initial Release</td>
            <td>Initial document creation</td>
            <td>Document Control</td>
        </tr>
    </tbody>
</table>

<h2>8. REFERENCES</h2>
<ul>
    <li>ISO 13485:2016 Medical devices — Quality management systems</li>
    <li>21 CFR Part 820 Quality System Regulation</li>
    <li>Company Quality Manual</li>
</ul>
HTML;
    }
}


<?php
/**
 * Activity text strings.
 * Is used for all the text within activity logs & notifications.
 * 
 * SOP Terminology:
 *   Page → SOP Document
 *   Chapter → SOP Group
 *   Book → Knowledge Domain
 *   Shelf → Department
 */
return [

    // SOP Documents (Pages)
    'page_create'                 => 'created SOP document',
    'page_create_notification'    => 'SOP Document successfully created',
    'page_update'                 => 'updated SOP document',
    'page_update_notification'    => 'SOP Document successfully updated',
    'page_delete'                 => 'deleted SOP document',
    'page_delete_notification'    => 'SOP Document successfully deleted',
    'page_restore'                => 'restored SOP document',
    'page_restore_notification'   => 'SOP Document successfully restored',
    'page_move'                   => 'moved SOP document',
    'page_move_notification'      => 'SOP Document successfully moved',

    // SOP Groups (Chapters)
    'chapter_create'              => 'created SOP group',
    'chapter_create_notification' => 'SOP Group successfully created',
    'chapter_update'              => 'updated SOP group',
    'chapter_update_notification' => 'SOP Group successfully updated',
    'chapter_delete'              => 'deleted SOP group',
    'chapter_delete_notification' => 'SOP Group successfully deleted',
    'chapter_move'                => 'moved SOP group',
    'chapter_move_notification' => 'SOP Group successfully moved',

    // Knowledge Domains (Books)
    'book_create'                 => 'created knowledge domain',
    'book_create_notification'    => 'Knowledge Domain successfully created',
    'book_create_from_chapter'              => 'converted SOP group to knowledge domain',
    'book_create_from_chapter_notification' => 'SOP Group successfully converted to a knowledge domain',
    'book_update'                 => 'updated knowledge domain',
    'book_update_notification'    => 'Knowledge Domain successfully updated',
    'book_delete'                 => 'deleted knowledge domain',
    'book_delete_notification'    => 'Knowledge Domain successfully deleted',
    'book_sort'                   => 'sorted knowledge domain',
    'book_sort_notification'      => 'Knowledge Domain successfully re-sorted',

    // Departments (Bookshelves)
    'bookshelf_create'            => 'created department',
    'bookshelf_create_notification'    => 'Department successfully created',
    'bookshelf_create_from_book'    => 'converted knowledge domain to department',
    'bookshelf_create_from_book_notification'    => 'Knowledge Domain successfully converted to a department',
    'bookshelf_update'                 => 'updated department',
    'bookshelf_update_notification'    => 'Department successfully updated',
    'bookshelf_delete'                 => 'deleted department',
    'bookshelf_delete_notification'    => 'Department successfully deleted',

    // Revisions
    'revision_restore' => 'restored revision',
    'revision_delete' => 'deleted revision',
    'revision_delete_notification' => 'Revision successfully deleted',

    // Favourites
    'favourite_add_notification' => '":name" has been added to your favourites',
    'favourite_remove_notification' => '":name" has been removed from your favourites',

    // Watching
    'watch_update_level_notification' => 'Watch preferences successfully updated',

    // Auth
    'auth_login' => 'logged in',
    'auth_register' => 'registered as new user',
    'auth_password_reset_request' => 'requested user password reset',
    'auth_password_reset_update' => 'reset user password',
    'mfa_setup_method' => 'configured MFA method',
    'mfa_setup_method_notification' => 'Multi-factor method successfully configured',
    'mfa_remove_method' => 'removed MFA method',
    'mfa_remove_method_notification' => 'Multi-factor method successfully removed',

    // Settings
    'settings_update' => 'updated settings',
    'settings_update_notification' => 'Settings successfully updated',
    'maintenance_action_run' => 'ran maintenance action',

    // Webhooks
    'webhook_create' => 'created webhook',
    'webhook_create_notification' => 'Webhook successfully created',
    'webhook_update' => 'updated webhook',
    'webhook_update_notification' => 'Webhook successfully updated',
    'webhook_delete' => 'deleted webhook',
    'webhook_delete_notification' => 'Webhook successfully deleted',

    // Imports
    'import_create' => 'created import',
    'import_create_notification' => 'Import successfully uploaded',
    'import_run' => 'updated import',
    'import_run_notification' => 'Content successfully imported',
    'import_delete' => 'deleted import',
    'import_delete_notification' => 'Import successfully deleted',

    // Users
    'user_create' => 'created user',
    'user_create_notification' => 'User successfully created',
    'user_update' => 'updated user',
    'user_update_notification' => 'User successfully updated',
    'user_delete' => 'deleted user',
    'user_delete_notification' => 'User successfully removed',

    // API Tokens
    'api_token_create' => 'created API token',
    'api_token_create_notification' => 'API token successfully created',
    'api_token_update' => 'updated API token',
    'api_token_update_notification' => 'API token successfully updated',
    'api_token_delete' => 'deleted API token',
    'api_token_delete_notification' => 'API token successfully deleted',

    // Roles
    'role_create' => 'created role',
    'role_create_notification' => 'Role successfully created',
    'role_update' => 'updated role',
    'role_update_notification' => 'Role successfully updated',
    'role_delete' => 'deleted role',
    'role_delete_notification' => 'Role successfully deleted',

    // Recycle Bin
    'recycle_bin_empty' => 'emptied recycle bin',
    'recycle_bin_restore' => 'restored from recycle bin',
    'recycle_bin_destroy' => 'removed from recycle bin',

    // Comments
    'commented_on'                => 'commented on',
    'comment_create'              => 'added comment',
    'comment_update'              => 'updated comment',
    'comment_delete'              => 'deleted comment',

    // Sort Rules
    'sort_rule_create' => 'created sort rule',
    'sort_rule_create_notification' => 'Sort rule successfully created',
    'sort_rule_update' => 'updated sort rule',
    'sort_rule_update_notification' => 'Sort rule successfully updated',
    'sort_rule_delete' => 'deleted sort rule',
    'sort_rule_delete_notification' => 'Sort rule successfully deleted',

    // SOP Approval Workflow (New)
    'revision_submit_for_review' => 'submitted revision for review',
    'revision_submit_for_review_notification' => 'Revision submitted for review',
    'revision_approve' => 'approved revision',
    'revision_approve_notification' => 'Revision successfully approved',
    'revision_reject' => 'rejected revision',
    'revision_reject_notification' => 'Revision rejected',

    // Other
    'permissions_update'          => 'updated permissions',
];

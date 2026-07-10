<?php

$labels = array();

$labels['menutitle']     = 'Manage domain 2FA';
$labels['intro']         = 'Activate or deactivate two-factor authentication (Google Authenticator) for users in your email domain.';
$labels['introcount']    = 'Activate or deactivate two-factor authentication (Google Authenticator) for users in your email domain. Total accounts in domain: {total}.';
$labels['pageprev']      = '\u2039 Previous';
$labels['pagenext']      = 'Next \u203a';
$labels['search']        = 'Search';
$labels['searchplaceholder'] = 'Search by email...';
$labels['searchclear']   = 'Clear search';
$labels['email']         = 'Email';
$labels['status']        = '2FA status';
$labels['action']        = 'Action';
$labels['active']        = 'Active';
$labels['inactive']      = 'Inactive';
$labels['activate']      = 'Activate';
$labels['deactivate']    = 'Deactivate';
$labels['nosecret']      = 'Not configured';
$labels['nosecrettitle'] = 'This user has not configured Google Authenticator yet (no secret stored). They need to configure it themselves under Settings > Two-Factor Authentication before an admin can remotely re-enable it.';

$labels['admin2fa_error_forbidden']       = 'You are not allowed to perform this action.';
$labels['admin2fa_error_invalid_request'] = 'Invalid request.';
$labels['admin2fa_error_user_not_found']  = 'User not found.';
$labels['admin2fa_error_domain_mismatch'] = 'This user does not belong to your domain.';
$labels['admin2fa_error_no_secret']       = 'This user has not configured Google Authenticator yet (no secret). Ask them to configure it under Settings > Two-Factor Authentication before activating remotely.';
$labels['admin2fa_error_unknown']         = 'An unexpected error occurred.';

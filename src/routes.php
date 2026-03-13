<?php
/**
 * BofaDueDiligence - Routes
 * Format: 'path' => ['controller' => 'ClassName', 'method' => 'methodName', 'role' => 'required_role|null']
 */

return [
    // Auth
    'login'  => ['controller' => 'AuthController', 'method' => 'login', 'role' => null],
    'logout' => ['controller' => 'AuthController', 'method' => 'logout', 'role' => null],

    // Client routes
    'client/dashboard'      => ['controller' => 'ClientController', 'method' => 'dashboard', 'role' => 'client'],
    'client/pending'        => ['controller' => 'ClientController', 'method' => 'pendingFunds', 'role' => 'client'],
    'client/case'           => ['controller' => 'ClientController', 'method' => 'viewCase', 'role' => 'client'],
    'client/transfer'       => ['controller' => 'ClientController', 'method' => 'transfer', 'role' => 'client'],
    'client/documents'      => ['controller' => 'ClientController', 'method' => 'uploadDocument', 'role' => 'client'],
    'client/checklist'      => ['controller' => 'ClientController', 'method' => 'updateChecklist', 'role' => 'client'],
    'client/messages'       => ['controller' => 'ClientController', 'method' => 'messages', 'role' => 'client'],
    'client/send-message'   => ['controller' => 'ClientController', 'method' => 'sendMessage', 'role' => 'client'],
    'client/history'        => ['controller' => 'ClientController', 'method' => 'transferHistory', 'role' => 'client'],
    'client/profile'        => ['controller' => 'ClientController', 'method' => 'profile', 'role' => 'client'],
    'client/profile/update' => ['controller' => 'ClientController', 'method' => 'updateProfile', 'role' => 'client'],

    // Client - 20 Online Banking Options
    'client/account-summary'        => ['controller' => 'ClientController', 'method' => 'accountSummary', 'role' => 'client'],
    'client/compliance-center'      => ['controller' => 'ClientController', 'method' => 'complianceCenter', 'role' => 'client'],
    'client/kyc-verification'       => ['controller' => 'ClientController', 'method' => 'kycVerification', 'role' => 'client'],
    'client/risk-profile'           => ['controller' => 'ClientController', 'method' => 'riskProfile', 'role' => 'client'],
    'client/document-vault'         => ['controller' => 'ClientController', 'method' => 'documentVault', 'role' => 'client'],
    'client/secure-messages'        => ['controller' => 'ClientController', 'method' => 'secureMessages', 'role' => 'client'],
    'client/beneficiaries'          => ['controller' => 'ClientController', 'method' => 'beneficiaries', 'role' => 'client'],
    'client/statements'             => ['controller' => 'ClientController', 'method' => 'statements', 'role' => 'client'],
    'client/regulatory-alerts'      => ['controller' => 'ClientController', 'method' => 'regulatoryAlerts', 'role' => 'client'],
    'client/transaction-monitoring' => ['controller' => 'ClientController', 'method' => 'transactionMonitoring', 'role' => 'client'],
    'client/activity-log'           => ['controller' => 'ClientController', 'method' => 'activityLog', 'role' => 'client'],
    'client/tax-documents'          => ['controller' => 'ClientController', 'method' => 'taxDocuments', 'role' => 'client'],
    'client/declarations'           => ['controller' => 'ClientController', 'method' => 'declarations', 'role' => 'client'],
    'client/reports'                => ['controller' => 'ClientController', 'method' => 'reports', 'role' => 'client'],
    'client/compliance-training'    => ['controller' => 'ClientController', 'method' => 'complianceTraining', 'role' => 'client'],
    'client/security-settings'      => ['controller' => 'ClientController', 'method' => 'securitySettings', 'role' => 'client'],
    'client/help-support'           => ['controller' => 'ClientController', 'method' => 'helpSupport', 'role' => 'client'],

    // Agent routes
    'agent/dashboard'           => ['controller' => 'AgentController', 'method' => 'dashboard', 'role' => 'agent'],
    'agent/cases'               => ['controller' => 'AgentController', 'method' => 'caseList', 'role' => 'agent'],
    'agent/case'                => ['controller' => 'AgentController', 'method' => 'caseDetail', 'role' => 'agent'],
    'agent/case/update-status'  => ['controller' => 'AgentController', 'method' => 'updateStatus', 'role' => 'agent'],
    'agent/case/freeze'         => ['controller' => 'AgentController', 'method' => 'freezeFunds', 'role' => 'agent'],
    'agent/case/unfreeze'       => ['controller' => 'AgentController', 'method' => 'unfreezeFunds', 'role' => 'agent'],
    'agent/case/validate'       => ['controller' => 'AgentController', 'method' => 'validateCase', 'role' => 'agent'],
    'agent/case/reject'         => ['controller' => 'AgentController', 'method' => 'rejectCase', 'role' => 'agent'],
    'agent/case/request-docs'   => ['controller' => 'AgentController', 'method' => 'requestDocuments', 'role' => 'agent'],
    'agent/case/validate-doc'   => ['controller' => 'AgentController', 'method' => 'validateDocument', 'role' => 'agent'],
    'agent/case/reject-doc'     => ['controller' => 'AgentController', 'method' => 'rejectDocument', 'role' => 'agent'],
    'agent/case/add-checklist'  => ['controller' => 'AgentController', 'method' => 'addChecklistItem', 'role' => 'agent'],
    'agent/case/send-message'   => ['controller' => 'AgentController', 'method' => 'sendMessage', 'role' => 'agent'],

    // Admin routes
    'admin/dashboard'               => ['controller' => 'AdminController', 'method' => 'dashboard', 'role' => 'admin'],
    'admin/users'                   => ['controller' => 'AdminController', 'method' => 'userList', 'role' => 'admin'],
    'admin/users/create'            => ['controller' => 'AdminController', 'method' => 'createUser', 'role' => 'admin'],
    'admin/users/edit'              => ['controller' => 'AdminController', 'method' => 'editUser', 'role' => 'admin'],
    'admin/users/toggle'            => ['controller' => 'AdminController', 'method' => 'toggleUser', 'role' => 'admin'],
    'admin/users/reset-password'    => ['controller' => 'AdminController', 'method' => 'resetPassword', 'role' => 'admin'],
    'admin/users/assign-agent'      => ['controller' => 'AdminController', 'method' => 'assignAgent', 'role' => 'admin'],
    'admin/risk-countries'          => ['controller' => 'AdminController', 'method' => 'riskCountries', 'role' => 'admin'],
    'admin/risk-countries/save'     => ['controller' => 'AdminController', 'method' => 'saveRiskCountry', 'role' => 'admin'],
    'admin/risk-countries/delete'   => ['controller' => 'AdminController', 'method' => 'deleteRiskCountry', 'role' => 'admin'],
    'admin/risk-assets'             => ['controller' => 'AdminController', 'method' => 'riskAssets', 'role' => 'admin'],
    'admin/risk-assets/save'        => ['controller' => 'AdminController', 'method' => 'saveRiskAsset', 'role' => 'admin'],
    'admin/risk-assets/delete'      => ['controller' => 'AdminController', 'method' => 'deleteRiskAsset', 'role' => 'admin'],
    'admin/settings'                => ['controller' => 'AdminController', 'method' => 'settings', 'role' => 'admin'],
    'admin/settings/save'           => ['controller' => 'AdminController', 'method' => 'saveSettings', 'role' => 'admin'],
    'admin/audit'                   => ['controller' => 'AdminController', 'method' => 'auditLog', 'role' => 'admin'],
    'admin/cases'                   => ['controller' => 'AdminController', 'method' => 'allCases', 'role' => 'admin'],
    'admin/case'                    => ['controller' => 'AdminController', 'method' => 'caseDetail', 'role' => 'admin'],

    // Admin - Compliance Management
    'admin/compliance-overview'     => ['controller' => 'AdminController', 'method' => 'complianceOverview', 'role' => 'admin'],
    'admin/kyc-management'          => ['controller' => 'AdminController', 'method' => 'kycManagement', 'role' => 'admin'],
    'admin/client-risk-overview'    => ['controller' => 'AdminController', 'method' => 'clientRiskOverview', 'role' => 'admin'],
    'admin/banking-services'        => ['controller' => 'AdminController', 'method' => 'bankingServices', 'role' => 'admin'],

    // API / AJAX
    'api/notifications'      => ['controller' => 'ApiController', 'method' => 'getNotifications', 'role' => null],
    'api/notifications/read'  => ['controller' => 'ApiController', 'method' => 'markNotificationRead', 'role' => null],
    'api/search'              => ['controller' => 'ApiController', 'method' => 'search', 'role' => null],
    'api/export/csv'          => ['controller' => 'ApiController', 'method' => 'exportCSV', 'role' => null],
];

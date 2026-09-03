<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Rbac extends BaseConfig
{
    /**
     * Map of roles to their permitted actions.
     * Deny by default: If a role or permission is not here, it is denied.
     */
    public $permissions = [
        'ketua' => [
            'cash.view',
            'cash.create',
            'cash.delete',
            'inventory.view',
            'inventory.create',
            'inventory.borrow',
            'inventory.approve',
            'inventory.return',
            'event.view',
            'event.manage',
            'attendance.checkin',
            'attendance.manage',
            'voting.view',
            'voting.vote',
            'voting.manage',
            'announcement.view',
            'announcement.manage',
            'chat.read',
            'chat.send',
            'chat.manage',
            'members.view',
            'members.manage',
            'members.create',
            'members.change_role',
            'members.deactivate',
            'members.reset_password',
            'settings.manage',
            'report.view',
            'report.finance.view'
        ],
        'sekretaris' => [
            'cash.view',
            'inventory.view',
            'inventory.borrow',
            'inventory.return',
            'event.view',
            'attendance.checkin',
            'voting.view',
            'voting.vote',
            'announcement.view',
            'announcement.manage',
            'chat.read',
            'chat.send',
            'members.view',
            'report.view'
        ],
        'bendahara' => [
            'cash.view',
            'cash.create',
            'cash.delete',
            'report.finance.view',
            'inventory.view',
            'inventory.borrow',
            'inventory.return',
            'event.view',
            'attendance.checkin',
            'voting.view',
            'voting.vote',
            'announcement.view',
            'chat.read',
            'chat.send',
            'report.view'
        ],
        'pengelola' => [
            'cash.view',
            'inventory.view',
            'inventory.borrow',
            'inventory.return',
            'event.view',
            'event.manage',
            'attendance.checkin',
            'attendance.manage',
            'voting.view',
            'voting.vote',
            'announcement.view',
            'chat.read',
            'chat.send',
            'members.view'
        ],
        'anggota' => [
            'cash.view',
            'inventory.view',
            'inventory.borrow',
            'inventory.return',
            'event.view',
            'attendance.checkin',
            'voting.view',
            'voting.vote',
            'announcement.view',
            'chat.read',
            'chat.send',
            'members.view'
        ],
        'admin' => [ // Admin of the tenant (usually acts similarly to ketua but can manage core tenant structure)
            'cash.view',
            'cash.create',
            'cash.delete',
            'inventory.view',
            'inventory.create',
            'inventory.borrow',
            'inventory.approve',
            'inventory.return',
            'event.view',
            'event.manage',
            'attendance.checkin',
            'attendance.manage',
            'voting.view',
            'voting.vote',
            'voting.manage',
            'announcement.view',
            'announcement.manage',
            'chat.read',
            'chat.send',
            'chat.manage',
            'members.view',
            'members.manage',
            'members.create',
            'members.change_role',
            'members.deactivate',
            'members.reset_password',
            'settings.manage',
            'report.view',
            'report.finance.view'
        ]
    ];
}

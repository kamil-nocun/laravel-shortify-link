<?php

namespace App\Enums;

enum Permission: string
{
    case AdminAccess = 'admin.access';
    case UsersView = 'users.view';
    case UsersCreate = 'users.create';
    case UsersUpdate = 'users.update';
    case UsersDelete = 'users.delete';
    case UsersBlock = 'users.block';
    case UsersChangeRole = 'users.change_role';
    case AdministratorsManage = 'administrators.manage';
    case AdministratorsAssign = 'administrators.assign';
    case LinksCreateForUser = 'links.create_for_user';
    case LinksDisable = 'links.disable';
    case DomainsManageForUser = 'domains.manage_for_user';
    case DomainsDisable = 'domains.disable';
    case PackagesManage = 'packages.manage';
    case PackagesAssign = 'packages.assign';
    case RolesManage = 'roles.manage';
    case LinksManage = 'links.manage';
    case LinksStats = 'links.stats';
    case DomainsManage = 'domains.manage';
    case BillingManage = 'billing.manage';
    case PagesManage = 'pages.manage';

    public function label(): string
    {
        return match ($this) {
            self::AdminAccess => 'Access the admin panel',
            self::UsersView => 'View users',
            self::UsersCreate => 'Create users',
            self::UsersUpdate => 'Update users',
            self::UsersDelete => 'Delete users',
            self::UsersBlock => 'Block users',
            self::UsersChangeRole => 'Change user roles',
            self::AdministratorsManage => 'Manage administrators',
            self::AdministratorsAssign => 'Assign administrators to clients',
            self::LinksCreateForUser => 'Create links for users',
            self::LinksDisable => 'Disable links',
            self::DomainsManageForUser => 'Manage domains for users',
            self::DomainsDisable => 'Disable domains',
            self::PackagesManage => 'Manage packages',
            self::PackagesAssign => 'Assign packages',
            self::RolesManage => 'Manage roles',
            self::LinksManage => 'Manage own links',
            self::LinksStats => 'View link statistics',
            self::DomainsManage => 'Manage own domains',
            self::BillingManage => 'Manage billing',
            self::PagesManage => 'Manage pages',
        };
    }

    public function group(): string
    {
        return match ($this) {
            self::AdminAccess => 'Admin panel',
            self::UsersView, self::UsersCreate, self::UsersUpdate, self::UsersDelete, self::UsersBlock, self::UsersChangeRole => 'Users',
            self::AdministratorsManage, self::AdministratorsAssign => 'Administrators',
            self::LinksCreateForUser, self::LinksDisable => 'Links (admin)',
            self::DomainsManageForUser, self::DomainsDisable => 'Domains (admin)',
            self::PackagesManage, self::PackagesAssign => 'Packages',
            self::RolesManage => 'Roles',
            self::PagesManage => 'Pages',
            self::LinksManage, self::LinksStats, self::DomainsManage, self::BillingManage => 'Client features',
        };
    }

    /**
     * @return list<self>
     */
    public static function forClients(): array
    {
        return [self::LinksManage, self::LinksStats, self::DomainsManage, self::BillingManage];
    }
}

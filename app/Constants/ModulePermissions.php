<?php

namespace App\Constants;

class ModulePermissions
{
    /**
     * Users
     */
    public const LIST_USERS = 'list-users';

    public const CREATE_USERS = 'create-users';

    public const UPDATE_USERS = 'update-users';

    public const DELETE_USERS = 'delete-users';

    /**
     * Roles
     */
    public const LIST_ROLES = 'list-roles';

    public const CREATE_ROLES = 'create-roles';

    public const UPDATE_ROLES = 'update-roles';

    public const DELETE_ROLES = 'delete-roles';

    /**
     * Permissions
     */
    public const CREATE_PERMISSIONS = 'create-permissions';

    public const LIST_PERMISSIONS = 'list-permissions';

    public const UPDATE_PERMISSIONS = 'update-permissions';

    public const DELETE_PERMISSIONS = 'delete-permissions';

    /**
     * Cases
     */
    public const LIST_CASES = 'list-cases';

    public const CREATE_CASES = 'create-cases';

    public const UPDATE_CASES = 'update-cases';

    public const DELETE_CASES = 'delete-cases';

    /**
     * Hearings
     */
    public const LIST_HEARINGS = 'list-hearings';

    public const CREATE_HEARINGS = 'create-hearings';

    public const UPDATE_HEARINGS = 'update-hearings';

    public const DELETE_HEARINGS = 'delete-hearings';

    /**
     * Documents
     */
    public const LIST_DOCUMENTS = 'list-documents';

    public const VIEW_DOCUMENTS = 'view-documents';

    public const CREATE_DOCUMENTS = 'create-documents';

    public const DELETE_DOCUMENTS = 'delete-documents';

    /**
     * Clients
     */
    public const LIST_CLIENTS = 'list-clients';

    public const CREATE_CLIENTS = 'create-clients';

    public const UPDATE_CLIENTS = 'update-clients';

    public const DELETE_CLIENTS = 'delete-clients';

    /**
     * Expenses
     */
    public const LIST_EXPENSES = 'list-expenses';

    public const CREATE_EXPENSES = 'create-expenses';

    public const UPDATE_EXPENSES = 'update-expenses';

    public const DELETE_EXPENSES = 'delete-expenses';

    /**
     * Suspect
     */
    public const LIST_SUSPECT = 'list-suspect';

    public const CREATE_SUSPECT = 'create-suspect';

    public const UPDATE_SUSPECT = 'update-suspect';

    public const DELETE_SUSPECT = 'delete-suspect';

    /**
     * Task
     */
    public const LIST_TASK = 'list-task';

    public const CREATE_TASK = 'create-task';

    public const UPDATE_TASK = 'update-task';

    public const DELETE_TASK = 'delete-task';

    /**
     * Courts
     */
    public const LIST_COURTS = 'list-court';

    public const CREATE_COURTS = 'create-court';

    public const UPDATE_COURTS = 'update-court';

    public const DELETE_COURTS = 'delete-court';

    /**
     * Invoices
     */
    public const LIST_INVOICES = 'list-invoice';

    public const CREATE_INVOICES = 'create-invoice';

    public const UPDATE_INVOICES = 'update-invoice';

    public const DELETE_INVOICES = 'delete-invoice';

    public const VIEW_DASHBOARD = 'view-dashboard';

    /**
     * Time entries (billable hours)
     */
    public const LIST_TIME_ENTRIES = 'list-time-entries';

    public const CREATE_TIME_ENTRIES = 'create-time-entries';

    public const UPDATE_TIME_ENTRIES = 'update-time-entries';

    public const DELETE_TIME_ENTRIES = 'delete-time-entries';

    /**
     * Payments / receipts
     */
    public const LIST_PAYMENTS = 'list-payments';

    public const CREATE_PAYMENTS = 'create-payments';

    public const DELETE_PAYMENTS = 'delete-payments';

    /**
     * Trust (client) accounting
     */
    public const LIST_TRUST_TRANSACTIONS = 'list-trust-transactions';

    public const CREATE_TRUST_TRANSACTIONS = 'create-trust-transactions';

    public const VOID_TRUST_TRANSACTIONS = 'void-trust-transactions';

    /**
     * Exports / reporting
     */
    public const EXPORT_FIRM_DATA = 'export-firm-data';
}

export interface AccountCard {
    id: number;
    name: string;
    institution: string | null;
    type: string;
    type_label: string;
    currency: string;
    color: string;
    icon: string;
    status: string;
    status_label: string;
    include_in_net_worth: boolean;
    balance: string;
    balance_minor: number;
    notes?: string | null;
    opening_balance?: string;
    opening_balance_on?: string;
    is_liquid?: boolean;
    bucket?: string;
    bucket_label?: string;
}

export interface CurrencySnapshot {
    currency: string;
    liquidity: string;
    parked: string;
    investment_cash: string;
    invested: string;
    investments_total: string;
    net_worth: string;
    income_month: string;
    expense_month: string;
    savings_month: string;
    savings_month_minor: number;
    income_change_percent: number | null;
    expense_change_percent: number | null;
    comparison_available: boolean;
    liquidity_minor?: number;
    parked_minor?: number;
    investments_total_minor?: number;
    loans_net?: string;
    loans_net_minor?: number;
    net_worth_minor?: number;
}

export interface Position {
    instrument_id: number;
    symbol: string;
    name: string;
    kind_label: string;
    account_id: number;
    account: string;
    currency: string;
    quantity: string;
    quantity_units: number;
    cost: string;
    cost_minor: number;
    average_price: string;
    price: string | null;
    priced_on: string | null;
    price_source: string | null;
    value: string | null;
    value_minor: number | null;
    unrealized: string | null;
    unrealized_minor: number | null;
    unrealized_percent: number | null;
}

export interface InvestmentTotals {
    currency: string;
    cost: string;
    ledger: string;
    unassigned: string;
    unassigned_minor: number;
    priced_count: number;
    unpriced_count: number;
    value: string;
    unrealized: string;
    unrealized_minor: number;
    realized: string;
    realized_minor: number;
}

export interface MovementSummary {
    id: number;
    type: string;
    type_label: string;
    description: string;
    occurred_on: string;
    booked_on: string;
    amount: string;
    direction: 'in' | 'out' | 'neutral';
    category: string | null;
    counterparty: string | null;
    voided: boolean;
    status: string;
    status_label: string;
    origin: string;
    accounts: string[];
}

export interface MovementDetail extends MovementSummary {
    notes: string | null;
    void_reason: string | null;
    currency: string;
    category_id: number | null;
    counterparty_id: number | null;
    external_id: string | null;
    tags: string[];
    can_edit: boolean;
    can_void: boolean;
    is_trade?: boolean;
    lines: { account: string; side: string; amount: string }[];
    attachments: { id: number; name: string }[];
    related: { id: number; description: string; voided: boolean }[];
}

export interface Option {
    value: string;
    label: string;
    hint?: string;
}

export interface CategoryOption {
    id: number;
    name: string;
    kind: string;
    parent_id: number | null;
    kind_label?: string;
    is_system?: boolean;
}

export interface CounterpartyOption {
    id: number;
    name: string;
    type_label?: string;
    type?: string;
    notes?: string | null;
    movements_count?: number;
}

export interface AccountOption {
    id: number;
    name: string;
    currency: string;
    type_label: string;
}

export interface Paginator<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    total: number;
    from: number | null;
    to: number | null;
}

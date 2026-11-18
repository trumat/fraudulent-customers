/** Mirrors App\Http\Resources\CustomerResource. Every upstream field may be null (absent). */
export interface Customer {
    id: number;
    external_id: number | null;
    full_name: string;
    bsn: number | null;
    first_name: string | null;
    last_name: string | null;
    date_of_birth: string | null; // Y-m-d
    phone_number: string | null;
    email: string | null;
    tag: string | null;
    street: string | null;
    postcode: string | null;
    city: string | null;
    products: string[];
    ip_address: string | null;
    iban: string | null;
    last_invoice_date: string | null; // Y-m-d
    last_login_at: string | null; // Y-m-d H:i:s, no timezone
    is_fraudulent: boolean;
    fraud_reasons: string[];
}

/** Mirrors App\Http\Resources\ScanResource. `customers` is only present on single-scan endpoints. */
export interface Scan {
    id: number;
    scanned_at: string; // ISO 8601
    total_customers: number;
    fraudulent_count: number;
    customers?: Customer[];
}

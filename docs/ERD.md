# Entity Relationship Diagram

```mermaid
erDiagram
    USERS ||--o{ SHIPMENTS : "customer_id (1:N)"
    USERS ||--o{ SHIPMENTS : "created_by (1:N)"
    CARRIERS ||--o{ SHIPMENTS : "carrier_id (1:N)"
    LOCATIONS ||--o{ SHIPMENTS : "origin_id (1:N)"
    LOCATIONS ||--o{ SHIPMENTS : "destination_id (1:N)"
    SHIPMENTS ||--|{ SHIPMENT_ITEMS : "has (1:N)"
    SHIPMENTS ||--o{ TRACKING_EVENTS : "history (1:N)"
    SHIPMENTS ||--o{ SHIPMENT_EXCEPTIONS : "has (1:N)"
    LOCATIONS ||--o{ TRACKING_EVENTS : "location_id (0..1:N)"
    USERS ||--o{ TRACKING_EVENTS : "created_by"
    USERS ||--o{ SHIPMENT_EXCEPTIONS : "created_by / resolved_by"
    USERS ||--o{ NOTIFICATIONS : "notifiable (polymorphic)"
    USERS ||--o{ AUDIT_LOGS : "actor"
    SHIPMENT_EXCEPTIONS ||--o{ EXCEPTION_NOTES : "investigation notes"
    USERS ||--o{ SHIPMENT_EXCEPTIONS : "assigned_to"
    SHIPMENTS ||--o{ SHIPMENT_DOCUMENTS : "documents / POD"
    USERS ||--o{ SUPPORT_TICKETS : "requester"
    SHIPMENTS ||--o{ SUPPORT_TICKETS : "optional link"
    USERS ||--o| COURIERS : "courier profile"
    COURIERS ||--o{ DELIVERY_ASSIGNMENTS : "tasks"
    SHIPMENTS ||--o{ DELIVERY_ASSIGNMENTS : "assigned"
    DELIVERY_ASSIGNMENTS ||--o{ COURIER_LOCATIONS : "gps history"
    SUPPORT_TICKETS ||--o{ SUPPORT_REPLIES : "conversation"

    USERS { bigint id PK
            string email UK
            string role
            bool is_active }
    CARRIERS { bigint id PK
               string code UK }
    LOCATIONS { bigint id PK
                decimal latitude
                decimal longitude }
    SHIPMENTS { bigint id PK
                string shipment_number UK
                string status
                datetime expected_arrival_at
                datetime estimated_arrival_at
                bool is_delayed
                decimal delay_hours
                datetime deleted_at }
    SHIPMENT_ITEMS { bigint id PK }
    TRACKING_EVENTS { bigint id PK
                      string event_type
                      datetime occurred_at
                      bool is_exception }
    SHIPMENT_EXCEPTIONS { bigint id PK
                          string type
                          string severity
                          string status
                          datetime detected_at
                          datetime resolved_at }
    EXCEPTION_NOTES { bigint id PK }
    SHIPMENT_DOCUMENTS { bigint id PK
                         string type
                         string path }
    SUPPORT_TICKETS { bigint id PK
                      string reference UK
                      string status }
    SUPPORT_REPLIES { bigint id PK
                      bool is_internal }
    COURIERS { bigint id PK
               bool is_tracking
               string availability }
    DELIVERY_ASSIGNMENTS { bigint id PK
                           string status }
    COURIER_LOCATIONS { bigint id PK
                        decimal latitude
                        decimal longitude
                        string source }
    NOTIFICATIONS { uuid id PK }
    AUDIT_LOGS { bigint id PK
                 string action }
```

## Design decisions

- **Shipment → tracking_events is hasMany** and append-only: history must never be overwritten, so the current state lives on `shipments.status` while the journey lives in events.
- **Shipment → exceptions is hasMany**: one shipment can face several disruptions (customs hold, then congestion).
- **Locations are shared master data** referenced by origin, destination and events; events also copy name/coordinates so history stays correct if a location is later edited.
- **Delay snapshot** (`is_delayed`, `delay_hours`, `delay_severity`) is denormalised on `shipments` for fast filtering and dashboards; `EtaService` is the only writer.
- **Soft deletes** only on `shipments` (financial/audit relevance); everything else is restricted or cascaded.
- Foreign keys: `restrictOnDelete` for master data, `nullOnDelete` for user attribution, `cascadeOnDelete` for child rows.
- Indexes: `(status,is_delayed)`, `(customer_id,status)`, `(shipment_id,occurred_at)`, `expected_arrival_at`, `(entity_type,entity_id)`.

- **Documents** live on the private disk; `path` is hidden from JSON and files are served only by an authorised controller.
- **Support replies** carry `is_internal` so staff notes never reach the customer.
- Roles are an enum column on `users` (no separate roles/permissions tables) — a deliberate simplification documented in the README.
- **courier_locations** is append-only GPS history (device timestamp `recorded_at`, server `created_at`, `source` = device|demo); indexed by assignment/shipment/courier and time.
- **delivery_assignments** keeps every attempt (re-assignments and failures stay as history); at most one active assignment per shipment is enforced in `DeliveryService`.

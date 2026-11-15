# Data Dictionary

| Table | Column | Type | Notes |
|---|---|---|---|
| users | id, name, email (unique), password (hashed), phone, company | — | |
| users | role | string(20) | `admin`/`operator`/`customer`; not mass-assignable |
| users | is_active | bool | disabled users cannot log in; API tokens revoked |
| carriers | name, code (unique), mode, contact_email, is_active | — | mode: sea/air/road/rail |
| locations | name, city, country, type, latitude/longitude decimal(10,7) | — | unique (name, city) |
| shipments | shipment_number | string(30) unique | `RCY-YYYY-000001` |
| shipments | customer_id, carrier_id, origin_id, destination_id | FK | restrict on delete |
| shipments | cargo_type, weight_kg, volume_cbm | — | |
| shipments | expected_departure_at, expected_arrival_at | datetime | planned schedule |
| shipments | estimated_arrival_at | datetime null | current ETA; drives the delay rule |
| shipments | delivered_at | datetime null | set when status becomes DELIVERED |
| shipments | status | string(20) | CREATED, BOOKED, PICKED_UP, IN_TRANSIT, DELIVERED, CANCELLED |
| shipments | is_delayed, delay_hours, delay_severity | bool, decimal(8,2), LOW/MEDIUM/HIGH | written only by EtaService |
| shipment_items | shipment_id, description, quantity, weight_kg | — | cascade delete |
| tracking_events | shipment_id, event_type, status | — | event_type: status names, CHECKPOINT, EXCEPTION, EXCEPTION_RESOLVED, DELAY_DETECTED |
| tracking_events | location_id, location_name, latitude, longitude | nullable | position at event time |
| tracking_events | occurred_at, description, is_exception, created_by | — | append-only |
| shipment_exceptions | shipment_id, type, severity, status, description | — | status OPEN/INVESTIGATING/RESOLVED |
| shipment_exceptions | detected_at, resolved_at, resolved_by, resolution_note, created_by | — | |
| notifications | Laravel standard (uuid, type, notifiable, data, read_at) | — | database channel |
| audit_logs | user_id, action, entity_type, entity_id, old_values, new_values (json), ip_address, created_at | — | |
| personal_access_tokens | Sanctum | — | created by `install:api` |
| shipments | priority | string(10) | NORMAL, HIGH, URGENT |
| shipment_exceptions | assigned_to | FK users null | responsible operator |
| exception_notes | shipment_exception_id, user_id, body | — | internal, append-only |
| shipment_documents | shipment_id, type, title, original_name, path, mime_type, size | — | path never exposed; random storage name |
| shipment_documents | recipient_name, received_at, notes | nullable | proof-of-delivery metadata |
| support_tickets | reference (unique), user_id, shipment_id, assigned_to, subject, category, priority, status, resolved_at | — | status OPEN/IN_PROGRESS/RESOLVED/CLOSED |
| support_replies | support_ticket_id, user_id, body, is_internal | — | internal = staff only |
| couriers | user_id (unique), phone, vehicle_type, vehicle_plate, availability, is_active | — | availability OFFLINE/AVAILABLE/ON_DELIVERY |
| couriers | is_tracking, last_seen_at | bool, datetime | GPS consent flag and last point received |
| delivery_assignments | shipment_id, courier_id, assigned_by, status, assigned_at … completed_at | — | ASSIGNED, ACCEPTED, PICKED_UP, IN_TRANSIT, ARRIVED, COMPLETED, FAILED, CANCELLED |
| delivery_assignments | failure_reason, recipient_name, notes | text/string | failure and POD metadata |
| courier_locations | courier_id, delivery_assignment_id, shipment_id | FK | |
| courier_locations | latitude, longitude (decimal 10,7), accuracy m, speed_kmh, heading | — | from the device |
| courier_locations | source, recorded_at, created_at | device/demo, datetime | demo points are labelled in the UI |

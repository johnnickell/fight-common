/* Generated from schemas/realtime.schema.json. Do not edit. */

/**
 * PROTOTYPE union of explicitly public AccessControl realtime envelopes.
 */
export type RealtimeEnvelope = UsersPageInvalidatedV1 | CurrentUserLifecycleChangedV1;

export interface UsersPageInvalidatedV1 {
    message_id: string;
    event_name: 'access.user.deleted';
    schema_version: 1;
    occurred_at: string;
    topic: string;
    payload: {
        invalidate: 'access.users.page';
    };
    metadata: EnvelopeMetadata;
}
export interface EnvelopeMetadata {
    correlation_id: string;
}
export interface CurrentUserLifecycleChangedV1 {
    message_id: string;
    event_name: 'access.current-user.lifecycle-changed';
    schema_version: 1;
    occurred_at: string;
    topic: string;
    payload: {
        refresh: 'current-session';
    };
    metadata: EnvelopeMetadata;
}

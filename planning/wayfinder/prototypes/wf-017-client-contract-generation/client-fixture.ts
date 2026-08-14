import type { RealtimeEnvelope } from './generated/realtime.js';
import type { UserView } from './generated/http.js';

export function routeRealtime(envelope: RealtimeEnvelope): 'users' | 'current-user' {
    if (envelope.event_name === 'access.user.deleted') {
        const target: 'access.users.page' = envelope.payload.invalidate;

        return target === 'access.users.page' ? 'users' : 'users';
    }

    const target: 'current-session' = envelope.payload.refresh;

    return target === 'current-session' ? 'current-user' : 'current-user';
}

export function renderUser(user: UserView): string {
    return `${user.email} (${user.state})`;
}

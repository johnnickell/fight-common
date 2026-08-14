export interface paths {
    readonly "/api/v1/access/users": {
        readonly parameters: {
            readonly query?: never;
            readonly header?: never;
            readonly path?: never;
            readonly cookie?: never;
        };
        readonly get: operations["listUsers"];
        readonly put?: never;
        readonly post?: never;
        readonly delete?: never;
        readonly options?: never;
        readonly head?: never;
        readonly patch?: never;
        readonly trace?: never;
    };
}
export type webhooks = Record<string, never>;
export interface components {
    schemas: {
        readonly JSendFailure: {
            readonly data: {
                /** @enum {string} */
                readonly reason: "authentication_required" | "forbidden";
            };
            /** @constant */
            readonly status: "fail";
        };
        readonly PaginationView: {
            readonly page: number;
            readonly per_page: number;
            readonly total: number;
        };
        readonly UserListData: {
            readonly pagination: components["schemas"]["PaginationView"];
            readonly users: readonly components["schemas"]["UserView"][];
        };
        readonly UserListSuccess: {
            readonly data: components["schemas"]["UserListData"];
            /** @constant */
            readonly status: "success";
        };
        readonly UserView: {
            /** Format: email */
            readonly email: string;
            /** Format: uuid */
            readonly id: string;
            readonly role_ids: readonly string[];
            /** @enum {string} */
            readonly state: "pending_activation" | "active" | "disabled" | "deleted";
        };
    };
    responses: {
        /** @description No current principal */
        readonly AuthenticationRequired: {
            headers: {
                readonly [name: string]: unknown;
            };
            content: {
                readonly "application/json": components["schemas"]["JSendFailure"];
            };
        };
        /** @description Current principal lacks LIST_USERS */
        readonly Forbidden: {
            headers: {
                readonly [name: string]: unknown;
            };
            content: {
                readonly "application/json": components["schemas"]["JSendFailure"];
            };
        };
    };
    parameters: never;
    requestBodies: never;
    headers: never;
    pathItems: never;
}
export type JSendFailure = components['schemas']['JSendFailure'];
export type PaginationView = components['schemas']['PaginationView'];
export type UserListData = components['schemas']['UserListData'];
export type UserListSuccess = components['schemas']['UserListSuccess'];
export type UserView = components['schemas']['UserView'];
export type ResponseAuthenticationRequired = components['responses']['AuthenticationRequired'];
export type ResponseForbidden = components['responses']['Forbidden'];
export type $defs = Record<string, never>;
export interface operations {
    readonly listUsers: {
        readonly parameters: {
            readonly query?: never;
            readonly header?: never;
            readonly path?: never;
            readonly cookie?: never;
        };
        readonly requestBody?: never;
        readonly responses: {
            /** @description Authorized paginated user list */
            readonly 200: {
                headers: {
                    readonly [name: string]: unknown;
                };
                content: {
                    readonly "application/json": components["schemas"]["UserListSuccess"];
                };
            };
            readonly 401: components["responses"]["AuthenticationRequired"];
            readonly 403: components["responses"]["Forbidden"];
        };
    };
}

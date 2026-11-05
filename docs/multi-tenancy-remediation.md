# QR code tenancy remediation

CMS Core `filament-cms.tenancy.enabled` is the sole mode switch. Its package default is false. Standalone and single-site installations need no tenant context; existing APIs, generation filenames and permissions remain available. Site-owned rows use tenant_id=0. Multi-tenant installations require CMS TenantContext and fail closed when absent.

The initial create migration includes tenant_id default 0 and a composite unique index on tenant_id/internal_name. This is a new package; no upgrade/alter migrations are shipped. Keep scene globally unique because the current package uses one shared Mini App client. Do not introduce per-tenant WeChat credentials in this change. Existing rows remain site-owned (0); enabling tenancy does not silently assign them to a tenant. Ownership changes are prohibited through ordinary model writes. Moving existing rows into a tenant requires an explicit, audited host data migration.

Model scope handles isolation for UI, business queries and relations. Disable redundant Filament resource scoping. Creation sets ownership; updates/deletes check it. HasQrcodes verifies parent ownership before external calls. Generation actions authorize records. In tenant mode, files use tenant and app-specific subdirectories; single mode retains original paths. Public disks remain public URLs and do not provide access-controlled downloads.

Validation covers single-site operation without CMS context, two-tenant query isolation, forged ownership, missing context, association ownership, scoped name uniqueness, global scene uniqueness, and file namespace separation. Update English and Chinese README. Do not modify composer.lock.

## Audit
Standard Filament record writes use the Audit package framework listeners. Custom generate actions emit qrcode.generate_web or qrcode.generate_mini_app through the optional CMS Operations API after successful storage and record update. QR payloads use operationAuditPayload() to allowlist non-secret fields; URLs, scene, raw additional data, paths and image data are excluded before enqueue. The Audit listener honors this explicit model payload hook for standard writes. Audit remains optional and no Fayne\Audit class is imported by the QR package.

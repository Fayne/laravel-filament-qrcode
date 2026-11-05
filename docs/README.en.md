<p align="center"><img src="https://res.cloudinary.com/fayne/image/upload/v1787643269/fayne_logo.png" width="300" alt="Fayne"></p>

# Laravel Filament QR Code

[简体中文](../README.md) | English

Web URL and WeChat Mini App QR generation for PHP 8.4+, Laravel 13 and Filament 5. Single-site use is the default. CMS Core tenancy and operation auditing are optional integrations.

## Features and scope

- Generate PNG web QR codes without WeChat credentials.
- Generate Mini App codes with scene, page, width, color, transparency and environment settings.
- Filament create/edit/filter/delete screens and explicit generation actions.
- Polymorphic `HasQrcodes` relationships for business models.
- Optional tenant isolation for records, associations and generated file paths.
- Optional administrator write auditing through CMS Core Operations and the Audit package.
- Module-owned permissions and translated permission labels.

Expiration is metadata, not a mechanism to revoke existing QR images. Public storage URLs remain public. Private download authorization and tenant-specific WeChat credentials are outside this package's scope.

## 1. Installation

Configure an authorized Composer source for this private package, then run:

```bash
composer require fayne/laravel-filament-qrcode
php artisan cms:qrcode:install
php artisan filament:assets
```

PHP GD is required. The initial migration contains the final tenant column and indexes and targets fresh databases. No incremental upgrade migration is supplied. Review existing development schemas separately; never use `migrate:fresh` on a database containing business data.

Register the plugin in your PanelProvider:

```php
use Fayne\QrCode\Filament\QrcodePlugin;
$panel->plugins([QrcodePlugin::make()]);
```

Authentication and policies use the host panel. With CMS Core, run `php artisan cms:install` to initialize the combined permission catalog and explicitly grant new permissions to existing roles. Standalone hosts use `viewAnyQrcode`, `viewQrcode`, `createQrcode`, `updateQrcode` and `deleteQrcode`. Multi-tenant CMS permissions use the `tenant.` prefix. Installation does not expand administrator privileges.

## 2. Configuration

```php
// config/qrcode.php
return [
    'allowed_types' => ['wechat_mini_app', 'web_url'],
    'storage_disks' => ['public'],
    'default_path' => env('QRCODE_DEFAULT_STORAGE_PATH', 'images/qrcodes'),
];
```

You may enable one type only. Existing record types cannot be changed. Web URLs must use HTTP/HTTPS and cannot contain embedded credentials. Failed storage does not persist a generated URL.

Mini App credentials come from the host's EasyWeChat configuration, defaulting to `WECHAT_MINI_APP_APPID` and `WECHAT_MINI_APP_SECRET`. The environment comes from `easywechat.mini_app.qrcode.env_version`. Web generation does not require these credentials. Never commit credentials or store them in record payloads.

## 3. Administration

Create a record with its internal name and type-specific inputs. Use the corresponding list action to generate an image with a width of 280–1280 and a permitted storage disk. Successful image URLs are stored in `urls`. Changing a web target clears previous generated URLs. `expired_at` does not enforce expiry at the target business route.

## 4. Business model usage

```php
use Fayne\QrCode\Contracts\Qrcodeable;
use Fayne\QrCode\Traits\HasQrcodes;
use Illuminate\Database\Eloquent\Model;

class Product extends Model implements Qrcodeable
{
    use HasQrcodes;
}

$qrcode = $product->generateWebQrcode(
    name: 'product-link',
    url: 'https://example.com/products/1',
    options: ['disk' => 'public', 'path' => 'images/qrcodes', 'width' => 430],
);
$result = $product->generateQrcode(
    name: 'product-mini-app',
    page: 'pages/product/detail',
    scene: 'product-1',
    styles: ['width' => 430],
    options: ['disk' => 'public'],
);
```

`$result` contains `qrcode` and `result`; `$product->qrcodes` reads associated records. The low-level `WebQrcodeGenerator::getQrcodeOutput()` and `Generator::getQrcodeOutput()` produce non-persisted outputs without tenant context, including login QR scenarios. Direct model calls are not automatically treated as administrator operations: custom API and queue flows must use the host's outer CMS Operations boundary.

## 5. Single-site and multi-tenant modes

`filament-cms.tenancy.enabled` defaults to false, including when CMS Core is absent. Site records use tenant_id=0; no tenant selection or context is required, and existing APIs and storage paths remain unchanged.

When CMS Core tenancy is enabled, current TenantContext is mandatory for record queries and writes. Creation assigns ownership; forged ownership and ownership changes are rejected. Names are unique within a tenant; association owners must belong to the current tenant. Generated paths include tenant and application identity. The resource disables redundant Filament scoping and relies on the model scope.

Scene remains globally unique because the current generator uses a shared Mini App client. Workers and CLI commands must restore tenant context explicitly. Switching modes does not merge or assign records. Site-owned rows do not silently become tenant records. See the [remediation design](multi-tenancy-remediation.md).

## 6. Administrator operation auditing

With `fayne/laravel-filament-audit` installed and enabled, standard create/edit/delete operations use the Audit package's Filament adapters. Custom generation actions emit `qrcode.generate_web` and `qrcode.generate_mini_app` only after storage and record update succeed. Failures do not emit successful completion.

Payloads allowlist internal name, type, expiry, width, disk and styling parameters. URLs, scene, page/storage paths, additional data and image data are excluded. The framework listener honors the model's `operationAuditPayload()` hook before enqueueing standard writes. Actor, target and tenant context are provided by the common operation infrastructure. Outer transaction rollback suppresses audit dispatch.

Follow the [Audit installation guide](../../laravel-filament-audit/README.md): enable `FILAMENT_AUDIT_ENABLED=true`, initialize permissions, run `php artisan audit:check`, and start the configured worker, e.g. `php artisan queue:work database --queue=audit`. An enqueued record is not necessarily persisted until consumed. Audit absence, disabled collection or subscriber failure does not break business operations. Delivery is not strongly consistent; retention and access rules belong to the Audit package.

## 7. Testing and deployment

Run `composer install`, `composer test`, `composer format`, and `composer analyse` from the package directory. Tests use in-memory SQLite and do not migrate host business databases. Validate real storage URLs, WeChat credentials, device scanning, tenant isolation and audit queue consumption separately before production. Refresh configuration caches and restart persistent web/queue workers after configuration changes.

## Collaboration and contributions

Authorized users may submit reproducible bug reports, feature requests or pull requests. Use a feature branch, include relevant tests and state service/device acceptance gaps. Do not submit credentials, personal data or customer QR contents.

Contact [Fayne Guo](mailto:fayne.guo@gmail.com) for licensing, integration and commercial cooperation.

## License

This package uses a [proprietary license](../LICENSE.md). Use, copying and redistribution require explicit authorization. Source access does not grant open-source redistribution rights.

## Installation command

`php artisan cms:qrcode:install` runs registered migrations, publishes `qrcode.php` only when missing, and initializes this module's permission definitions when permission tables are available. CMS Core uses tenant-prefixed keys; standalone permission hosts use the original names. Repeated runs preserve configuration and existing role grants. The command does not generate images, demo records or credentials, or automatically register the panel plugin.

Use `php artisan cms:qrcode:install --force --no-interaction` for production deployments. Migration/publication failure stops installation; unavailable permission tables produce a host-authorization warning. The command does not recursively invoke `cms:install`. Refresh cached configuration through your deployment workflow and configure storage, WeChat credentials and administrator permissions explicitly.


When orchestrated by `cms:install`, installed modules publish their configuration and migrations before the first migration pass. The shared installer then migrates and seeds permissions once, creates accounts/tenants, and generates demo content. Nested module commands reuse the prepared infrastructure instead of publishing or migrating again. Running a module install command independently still performs its complete setup. Preparation state is confined to the command run and restored on success or failure.

## Automatic CMS module registration

With CMS Core installed, this package registers its module through its Laravel ServiceProvider. Panels extending `Fayne\CMS\Providers\AdminPanelProvider` / `TenantPanelProvider` automatically connect installed modules: business UI belongs to the ordinary panel in single-site mode and the workspace in multi-tenant mode. Installation grants no account permissions; policies still control navigation and access.

In a fully custom PanelProvider, configure explicit plugins first, then return `app(\Fayne\CMS\Support\Modules\ModuleRegistry::class)->applyTo($panel)`. An explicitly configured plugin with the same ID is preserved. Do not register the same business plugin again after applying the registry. Earlier manual Plugin examples remain applicable to standalone or fully custom panels.

Registered modules are enabled by default in this phase. There is no module-management UI, online installation/removal or runtime API/queue disabling. 

Migration files are maintained exclusively in `database/migrations`. The service provider loads them automatically and publishes them with the `qrcode-migrations` tag.

<p align="center"><img src="https://res.cloudinary.com/fayne/image/upload/v1787643269/fayne_logo.png" width="300" alt="Fayne"></p>

# Laravel Filament QR Code

简体中文 | [English](docs/README.en.md)

适用于 **PHP 8.4+、Laravel 13、Filament 5** 的网页二维码和微信小程序码管理包。默认单站点使用，不要求安装 CMS Core；多租户与操作审计是可选集成。

## 功能与边界

| 功能 | 说明 |
| --- | --- |
| 网页二维码 | 将 HTTP/HTTPS 地址生成 PNG，无需微信凭证 |
| 微信小程序码 | scene、页面路径、尺寸、颜色、透明背景和环境配置 |
| 后台管理 | 创建、编辑、筛选、删除，以及显式生成图片 |
| 业务关联 | HasQrcodes 多态关联，供业务模型生成和查询二维码 |
| 单站点 | 默认开启，保持原 API 和目录，无需租户上下文 |
| 可选多租户 | 跟随 CMS Core 开关，隔离记录、关联生成和图片目录 |
| 可选操作审计 | 通过 CMS Core 公共操作边界对接 Audit 包，记录管理员成功写操作 |
| 权限目录 | 向 CMS Core 注册本包权限定义及中英文显示名称 |

过期时间只作为元数据，不会让已经生成的图片自动失效。公开磁盘链接仍然公开；包不提供私有文件下载授权或租户独立微信凭证。

## 1. 安装

先为宿主 Composer 配置本私有包的软件源，再安装：

```bash
composer require fayne/laravel-filament-qrcode
php artisan cms:qrcode:install
php artisan filament:assets
```

要求 PHP 8.4+、GD、Laravel 13 和 Filament 5。迁移面向全新数据库：初始表直接包含租户字段与最终索引，不提供旧版本升级迁移。已有开发数据库需单独核对结构；不要在含业务数据的数据库执行 `migrate:fresh`。

在 PanelProvider 注册：

```php
use Fayne\QrCode\Filament\QrcodePlugin;

$panel->plugins([QrcodePlugin::make()]);
```

后台使用宿主 Panel 的认证与 Policy。安装 CMS Core 时运行 `php artisan cms:install` 合并初始化权限目录，已有角色需显式授予新权限；独立宿主使用相应的 `viewAnyQrcode`、`viewQrcode`、`createQrcode`、`updateQrcode`、`deleteQrcode` 权限。CMS 多租户使用 `tenant.*Qrcode` 权限。不会自动给管理员扩权。

## 2. 配置

```php
// config/qrcode.php
return [
    'allowed_types' => ['wechat_mini_app', 'web_url'],
    'storage_disks' => ['public'],
    'default_path' => env('QRCODE_DEFAULT_STORAGE_PATH', 'images/qrcodes'),
];
```

可只启用一种类型。类型专属参数会校验；已创建记录不能更改类型。网页地址禁止携带用户名/密码，必须是合法 HTTP/HTTPS URL。图片写入失败会报错，不将失败 URL 保存到记录。

微信凭证由宿主 EasyWeChat 配置提供，默认读取 `WECHAT_MINI_APP_APPID` 与 `WECHAT_MINI_APP_SECRET`；生成环境读取 `easywechat.mini_app.qrcode.env_version`。只生成网页二维码时不需要微信凭证。凭证不要写入字段内容或版本库。

## 3. 后台使用

1. 新建二维码，选择类型，填写内部名称与对应 URL 或 scene/小程序页面。
2. 在列表点击相应生成操作，选择尺寸与存储磁盘等参数。
3. 图片保存成功后，生成地址写入记录的 `urls`。修改网页目标地址会清空旧的生成地址，需要重新生成。

尺寸范围为 280–1280。`expired_at` 不负责拦截扫码后的业务访问；如需失效链接，请由目标业务路由实现。

## 4. 业务模型使用

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
// $result['qrcode'] 是记录，$result['result'] 是生成结果。
```

`$product->qrcodes` 可读取关联记录。底层 `WebQrcodeGenerator::getQrcodeOutput($url, $width)` 和 `Generator::getQrcodeOutput(...)` 可生成不持久化的图片输出；登录码等场景不依赖租户上下文。直接业务调用不会被模型 Observer 自动记作“管理员操作”；宿主自定义 API/队列应按自己的外层业务边界使用 CMS Operations。

## 5. 单站点与多租户

默认 `filament-cms.tenancy.enabled=false`（未安装 Core 时同样默认关闭）。记录归属为 `tenant_id=0`，无需选择租户；原有生成目录和 API 保持一致。

开启 CMS Core 多租户后：

- 所有记录查询和写入要求当前 `TenantContext`，缺失时拒绝操作。
- 创建时自动赋予归属，拒绝伪造归属和普通更新更换租户。
- 内部名称在租户内唯一；关联来源模型必须属于当前租户。
- 图片目录增加租户及小程序应用标识，避免相同参数覆盖其他租户文件。
- 后台关闭 Filament 重复租户约束，由模型作用域统一隔离。

当前共享同一个微信客户端，所以 `scene` 仍全局唯一。队列和 CLI 须显式恢复租户上下文。切换模式不自动分配或合并数据；站点记录 `0` 不自动变为某个租户的数据。详细规则见 [整改方案](docs/multi-tenancy-remediation.md)。

## 6. 管理员操作审计

安装并启用 `fayne/laravel-filament-audit` 后：

- 标准创建、编辑、删除由 Audit 的 Filament 事件适配器记录。
- 两个生成按钮分别产生 `qrcode.generate_web`、`qrcode.generate_mini_app`，在存储及记录更新成功后记录；失败不产生成功事件。
- 记录操作者、目标及租户上下文；只允许必要的名称、类型、过期时间、尺寸、磁盘和样式参数进入载荷。目标 URL、scene、页面路径、additional_data、图片 URL 和图片数据不进入审计载荷。
- 有外层事务时，审计遵循最外层提交；审计未安装、关闭或订阅者失败时不阻断业务。标准操作不重复记录生成事件。

按 Audit 包说明执行迁移、权限初始化及 `php artisan audit:check`。启用 `FILAMENT_AUDIT_ENABLED=true` 并启动相应消费者，例如 `php artisan queue:work database --queue=audit`。未消费队列时不能认为记录已入库。队列重试、留存、查看范围和投递限制以 [Audit 文档](../laravel-filament-audit/README.md) 为准；本包不保证强一致合规投递。

## 7. 测试与部署

在包目录执行 `composer install`、`composer test`、`composer format`、`composer analyse`。测试使用内存 SQLite，不迁移宿主业务数据库。生产需单独验证实际磁盘 URL、微信凭证、设备扫码、租户隔离及审计队列消费。修改配置后刷新缓存并重启长驻 Web/队列进程。

## 合作与贡献

欢迎提交带复现步骤的缺陷说明、功能需求或授权范围内的合并请求。贡献前请确认使用授权，创建独立分支，补充相关测试，并说明尚未验证的服务或设备场景。请勿提交凭证、个人数据或客户二维码内容。

授权、集成及商业合作请联系 [Fayne Guo](mailto:fayne.guo@gmail.com)。

## License

本软件采用[专有许可证](LICENSE.md)。使用、复制和分发需获得权利人的明确授权；不是开源许可证，不因可读取源码而获得再分发权。

## 安装命令

`php artisan cms:qrcode:install` 执行所有已注册迁移，只在配置文件不存在时发布 `qrcode.php`，并在权限表可用时初始化本包声明的权限定义（CMS Core 使用 tenant 前缀，独立权限宿主使用原有名称）。可重复执行，不覆盖配置或已有角色授权。安装不会生成图片、演示数据、微信凭证或自动注册 Panel。

生产部署使用 `php artisan cms:qrcode:install --force --no-interaction`。迁移或配置发布失败立即停止；权限表不存在时提示宿主先完成授权接入。此命令不调用 `cms:install`，避免模块安装互相递归。配置缓存存在时按部署流程刷新，随后显式配置磁盘、微信凭证和管理员授权。

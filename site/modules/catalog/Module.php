<?php
declare(strict_types=1);

namespace AgeCMS\Modules\catalog;

use AgeCMS\Actor;
use AgeCMS\AdminModuleInterface;
use AgeCMS\AgeCMSException;
use AgeCMS\ModuleRequest;
use AgeCMS\ModuleResponse;
use AgeCMS\NotFoundException;
use AgeCMS\SitemapModuleInterface;

require_once __DIR__ . '/src/Slug.php';
require_once __DIR__ . '/src/Catalog.php';
require_once __DIR__ . '/src/Csv.php';
require_once __DIR__ . '/src/PublicPages.php';
require_once __DIR__ . '/src/AdminPages.php';

/**
 * Каталог красок сайта колеровки: страницы /katalog и /katalog/{slug}, раздел «Каталог» в админке
 * (поиск, фильтры, массовые правки цен, карточка, фото, импорт и выгрузка CSV) и API Catalog для блоков темы.
 */
final class Module implements SitemapModuleInterface, AdminModuleInterface
{
    public function register(): void
    {
    }

    public function callSkill(string $skill, array $args, Actor $actor): array
    {
        throw new AgeCMSException("Unknown skill {$skill}");
    }

    public function sitemapUrls(): iterable
    {
        return Catalog::sitemap(1000);
    }

    public function handle(ModuleRequest $request): ModuleResponse
    {
        if ($request->path === '/katalog') {
            return PublicPages::listing($request->query);
        }
        $slug = $request->param('slug');
        $item = preg_match('/^[a-z0-9-]{1,64}$/', $slug) === 1 ? Catalog::item($slug) : null;
        if ($item === null) {
            throw new NotFoundException('Товар не найден или снят с показа');
        }
        return PublicPages::product($item);
    }

    public function handleAdmin(ModuleRequest $request, Actor $actor): ModuleResponse
    {
        return AdminPages::handle($request, $actor);
    }
}

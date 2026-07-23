<?php
declare(strict_types=1);
namespace Tests\Unit;

use App\Http\Request;
use App\Support\ConfigLoader;
use App\Support\UrlGenerator;
use PHPUnit\Framework\TestCase;

final class StagingReadinessTest extends TestCase
{
    public function testNonRootUrlsAssetsAndRequestsRemainInsideBasePath():void
    {
        $urls=new UrlGenerator('https://www.dev-sandbox.it','/iaslab-projects');
        self::assertSame('https://www.dev-sandbox.it/iaslab-projects/',$urls->to('/'));
        self::assertSame('https://www.dev-sandbox.it/iaslab-projects/projects/7?year=2026',$urls->to('/projects/7',['year'=>2026]));
        self::assertSame('https://www.dev-sandbox.it/iaslab-projects/assets/css/app.css',$urls->asset('css/app.css'));
        $request=Request::fromGlobals(['REQUEST_METHOD'=>'GET','REQUEST_URI'=>'/iaslab-projects/projects/7?year=2026'],['year'=>'2026'],[],'/iaslab-projects');
        self::assertSame('/projects/7',$request->path());
        $session=(string)file_get_contents(dirname(__DIR__,2).'/bootstrap/app.php');
        self::assertStringContainsString("config->get('app.base_path','')",$session);
    }

    public function testEnvironmentOverridesUseDocumentedNames():void
    {
        $values=['APP_ENV'=>'staging','APP_DEBUG'=>'true','APP_URL'=>'https://stage.example.test','APP_BASE_PATH'=>'/iaslab-projects','APP_CLEAN_URLS'=>'false','APP_VERSION'=>'test-release'];
        $originals=[];foreach(array_keys($values)as$name)$originals[$name]=getenv($name);
        foreach($values as$name=>$value)putenv($name.'='.$value);
        try{$config=(new ConfigLoader(dirname(__DIR__,2)))->load();}
        finally{foreach($originals as$name=>$value)putenv($value===false?$name:$name.'='.$value);}
        self::assertSame('staging',$config->get('app.environment'));
        self::assertTrue($config->get('app.debug'));
        self::assertSame('https://stage.example.test',$config->get('app.base_url'));
        self::assertSame('/iaslab-projects',$config->get('app.base_path'));
        self::assertFalse($config->get('app.clean_urls'));
        self::assertSame('test-release',$config->get('app.version'));
    }

    public function testSharedHostingEntrypointAndProtectionsArePackaged():void
    {
        $root=dirname(__DIR__,2);
        self::assertFileExists($root.'/index.php');
        self::assertFileExists($root.'/.htaccess');
        $rules=(string)file_get_contents($root.'/.htaccess');
        self::assertStringContainsString('public/assets',$rules);
        self::assertStringContainsString('config|database|docs|storage|tests|vendor|views',$rules);
        self::assertStringContainsString('RewriteRule ^ index.php',$rules);
    }

    public function testSystemDiagnosticsAreAdminOnlyAndDoNotRenderSecrets():void
    {
        $root=dirname(__DIR__,2);
        $controller=(string)file_get_contents($root.'/app/Controllers/AdminSystemController.php');
        $view=(string)file_get_contents($root.'/views/admin/system.php');
        self::assertStringContainsString('if(!$user->isAdmin())',$controller);
        self::assertStringNotContainsString('DB_PASSWORD',$view);
        self::assertStringNotContainsString('.env',$view);
        self::assertStringContainsString('Users without linked Person',$view);
        self::assertStringContainsString('Migrations',$view);
        self::assertStringContainsString('Writable directories',$view);
    }

    public function testStagingCannotEnableDebugOrDevelopmentRoutes():void
    {
        $bootstrap=(string)file_get_contents(dirname(__DIR__,2).'/bootstrap/app.php');
        self::assertStringContainsString("in_array(\$environment, ['development','local','testing'], true)",$bootstrap);
        self::assertStringNotContainsString("\$environment !== 'production'",$bootstrap);
    }
}

<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Auth\Authorization;
use App\Database\ConnectionFactory;
use App\Exceptions\AuthorizationException;
use App\Http\Response;
use App\Support\Config;
use App\Support\View;
use PDO;
use Throwable;

final class AdminSystemController
{
    public function __construct(
        private readonly View $view,
        private readonly Authorization $authorization,
        private readonly ConnectionFactory $connections,
        private readonly Config $config,
        private readonly string $projectRoot,
        private readonly string $logDirectory,
    ){}

    public function show():Response
    {
        $user=$this->authorization->user();
        if(!$user->isAdmin())throw new AuthorizationException('System diagnostics are restricted to administrators.');
        $database=['connected'=>false,'message'=>'Unavailable'];
        $counts=['users'=>null,'people'=>null,'unlinkedUsers'=>null];
        $expected=$this->expectedMigrations();$migrations=['applied'=>[],'expected'=>$expected,'missing'=>$expected];
        try{
            $pdo=$this->connections->create();$pdo->query('SELECT 1');$database=['connected'=>true,'message'=>'Connected'];
            $counts=[
                'users'=>(int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
                'people'=>(int)$pdo->query('SELECT COUNT(*) FROM people')->fetchColumn(),
                'unlinkedUsers'=>(int)$pdo->query('SELECT COUNT(*) FROM users u LEFT JOIN people p ON p.user_id=u.id WHERE p.id IS NULL')->fetchColumn(),
            ];
            $migrations['applied']=$this->appliedMigrations($pdo);
            $migrations['missing']=array_values(array_diff($migrations['expected'],$migrations['applied']));
        }catch(Throwable $exception){error_log($exception->__toString());}
        $writable=[
            ['label'=>'Log directory','path'=>$this->relative($this->logDirectory),'writable'=>is_dir($this->logDirectory)&&is_writable($this->logDirectory)],
            ['label'=>'Session directory','path'=>(string)(session_save_path()?:sys_get_temp_dir()),'writable'=>is_writable(session_save_path()?:sys_get_temp_dir())],
        ];
        return new Response($this->view->render('admin/system',[
            'title'=>'System diagnostics','phpVersion'=>PHP_VERSION,'applicationVersion'=>(string)$this->config->get('app.version','unknown'),
            'environment'=>$this->config->requireString('app.environment'),'baseUrl'=>$this->config->requireString('app.base_url'),
            'basePath'=>(string)$this->config->get('app.base_path',''),'database'=>$database,'counts'=>$counts,'migrations'=>$migrations,'writable'=>$writable,
        ]));
    }
    /** @return list<string> */
    private function expectedMigrations():array{$files=glob($this->projectRoot.'/database/migrations/*.sql')?:[];$versions=[];foreach($files as$file)if(preg_match('/^(\\d+)_/',basename($file),$m))$versions[]=$m[1];sort($versions,SORT_STRING);return$versions;}
    /** @return list<string> */
    private function appliedMigrations(PDO$pdo):array{$values=$pdo->query('SELECT version FROM schema_versions ORDER BY version')->fetchAll(PDO::FETCH_COLUMN);return array_map('strval',$values);}
    private function relative(string$path):string{return str_starts_with($path,$this->projectRoot.'/')?substr($path,strlen($this->projectRoot)+1):$path;}
}

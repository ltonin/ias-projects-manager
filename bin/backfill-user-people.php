<?php
declare(strict_types=1);

use App\Database\ConnectionFactory;
use App\Services\UserPersonBackfillService;
use App\Support\ConfigLoader;

define('PROJECT_ROOT',dirname(__DIR__));require PROJECT_ROOT.'/bootstrap/autoload.php';
if(PHP_SAPI!=='cli'){fwrite(STDERR,"This command is available only from the command line.\n");exit(1);}
$options=getopt('',['dry-run','help']);
if(isset($options['help'])){fwrite(STDOUT,"Usage: php bin/backfill-user-people.php [--dry-run]\n");exit(0);}
try{
    $pdo=(new ConnectionFactory((new ConfigLoader(PROJECT_ROOT))->load()))->create();
    $report=(new UserPersonBackfillService($pdo))->run(isset($options['dry-run']));
    fwrite(STDOUT,(isset($options['dry-run'])?'Dry run — ':'').implode(', ',[
        'Users inspected: '.$report['users_inspected'],'already linked: '.$report['already_linked'],'unlinked found: '.$report['unlinked_found'],
        'People created: '.$report['people_created'],'links created: '.$report['links_created'],'ambiguous skipped: '.$report['ambiguous_skipped'],
        'excluded: '.$report['excluded'],'failures: '.$report['failures'],'remaining unlinked: '.$report['remaining_unlinked'],
    ]).".\n");exit(0);
}catch(Throwable$exception){fwrite(STDERR,'User–Person backfill failed; transaction rolled back: '.$exception->getMessage()."\n");exit(1);}

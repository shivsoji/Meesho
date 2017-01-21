<?php
require_once __DIR__.'/../vendor/autoload.php';
$app = new Silex\Application();
$app['debug'] = true;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Doctrine\DBAL\Schema\Table;
use Intervention\Image\ImageManagerStatic as Image;
Image::configure(array('driver' => 'gd'));
$redis = new Predis\Client();


$app->register(new Silex\Provider\MonologServiceProvider(), array(
    'monolog.logfile' => __DIR__.'/development.log',
));

$app->register(new Silex\Provider\DoctrineServiceProvider(), array(
    'db.options' => array(
        'path' => __DIR__ . '/development.sqlite',
        'driver' => 'pdo_sqlite'
    ),
));

$app->error(function (\Exception $e, $code) use($app) {
    return $app->json(array("error" => $e->getMessage()));
});

$schema = $app['db']->getSchemaManager();
if (!$schema->tablesExist('products')) {
    $products = new Table('products');
    $products->addColumn('id', 'integer', array('unsigned' => true, 'autoincrement' => true));
    $products->setPrimaryKey(array('id'));
    $products->addColumn('uid', 'string', array('length' => 32));
    $products->addUniqueIndex(array('uid'));
    $products->addColumn('name', 'string', array('length' => 255));
    $products->addColumn('price', 'integer');
    $schema->createTable($products);
}
if (!$schema->tablesExist('productImages')) {
    $pi = new Table('productImages');
    $pi->addColumn('id', 'integer', array('unsigned' => true, 'autoincrement' => true));
    $pi->setPrimaryKey(array('id'));
    $pi->addForeignKeyConstraint($products, array("id"), array("id"), array("onDelete" => "CASCADE"));
    $pi->addColumn('image', 'blob');
    $pi->addColumn('image_256', 'blob');
    $pi->addColumn('image_512', 'blob');
    $schema->createTable($pi);
}

$app->post('/meesho/api/upload', function (Request $request) use ($app,$redis) {
    $app['monolog']->debug(json_encode($request));
    $file = $request->files->get('image');
    $app['monolog']->debug($file);
    $uid = $request->get('uid');
    if ($file !== null && $uid !== null) {
        $file->move('/tmp/', $file->getClientOriginalName());
        $name = '/tmp/'.$file->getClientOriginalName();
        $ext = pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION);
        $data = file_get_contents($name);
        $base64 = 'data:image/' . $ext . ';base64,' . base64_encode($data);
        $redis->set($uid, $base64);
        $image_256 = Image::make($name)->resize(256, 256)->save('/tmp/'.$uid.'_256.'.$ext);
        $image_512 = Image::make($name)->resize(512, 512)->save('/tmp/'.$uid.'_512.'.$ext);
        process($uid, $file, $app, $redis);
        return new JsonResponse(array('status'=> true));
    } else {
        return new JsonResponse(array('status'=> false));
    }
});


$app->post('/meesho/api/post', function(Request $request) use ($app,$redis) {
    $name = $request->get('name');
    $price = $request->get('price');
    $uid = $request->get('uid');
    $file = $request->files->get('image');
    if ($name !== null && $price !== null && $uid !== null && $file !== null) {
        $app['db']->insert('products', array(
            'uid' => $uid,
            'name' => $name,
            'price' => $price
        ));
        process($uid, $file, $app, $redis);
        return new JsonResponse(array('status'=> true));
    } else {
        return new JsonResponse(array('status'=> false));
    }
});

$app->get('/meesho/api/product/{id}', function($id) use($app) {
    $sql = "SELECT productImages.*, products.* FROM products LEFT JOIN productImages ON products.id=productImages.id WHERE products.id = ?;";
    $product = $app['db']->fetchAssoc($sql, array((int) $id));
    return new JsonResponse($product);
});

$app->get('/meesho/api/products', function() use($app) {
    $sql = "SELECT productImages.*, products.* FROM products LEFT JOIN productImages ON products.id=productImages.id ORDER BY products.id DESC;";
    $products = $app['db']->fetchAll($sql);
    return new JsonResponse($products);
});

function process($uid, $file, $app, $redis) {
    $ext = pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION);
    $data = file_get_contents('/tmp/'.$uid.'_256.'.$ext);
    $m_256 = 'data:image/' . $ext . ';base64,' . base64_encode($data);
    $data = file_get_contents('/tmp/'.$uid.'_512.'.$ext);
    $m_512 = 'data:image/' . $ext . ';base64,' . base64_encode($data);
    $sql = "SELECT id FROM products WHERE products.uid = ?;";
    $product = $app['db']->fetchAssoc($sql, array((string) $uid));
    $app['monolog']->debug(json_encode($product));
    if ($product) {
        $sql = "SELECT id FROM productImages WHERE productImages.id = ?;";
        $images = $app['db']->fetchAssoc($sql, array((int) $product['id']));
        $app['monolog']->debug(json_encode($images));
        if (!$images) {
            $app['db']->insert('productImages', array(
                'id' => $product['id'],
                'image' => $redis->get($uid),
                'image_256' => $m_256,
                'image_512' => $m_512
            ));
        }
    }
}


$app->run();

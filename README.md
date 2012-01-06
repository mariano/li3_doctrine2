li3\_doctrine2 offers integration between [the most RAD PHP framework] [lithium]
and possibly the best PHP 5.3 ORM out there: [Doctrine2] [doctrine2]

# License #

li3\_doctrine2 is released under the [BSD License] [license].

# Installation #

It is recommended that you install li3\_doctrine2 as a GIT submodule, in order
to keep up with the latest upgrades. To do so, switch to the core directory
holding your lithium application, and do:

```bash
$ git submodule add https://github.com/mariano/li3_doctrine2.git app/libraries/li3_doctrine2
$ cd app/libraries/li3_doctrine2 && git submodule update --init
$ cd _source/doctrine2 && git submodule update --init
```

# Usage #

## Adding the li3\_doctrine2 library ##

Once you have downloaded li3\_doctrine2 and placed it in your `app/libraries`,
or your main `libraries` folder, you need to enable it by placing the following
at the end of your `app/config/bootstrap/libraries.php` file:

```php
Libraries::add('li3_doctrine2');
```

## Defining a connection ##

Setting up a connection with li3\_doctrine2 is easy. All you need to do is
add the following to your `app/config/bootstrap/connections.php` file (make
sure to edit the settings to match your host, without altering the `type`
setting):

```php
Connections::add('default', array(
    'type' => 'Doctrine',
    'driver' => 'pdo_mysql',
    'host' => 'localhost',
    'user' => 'root',
    'password' => 'password',
    'dbname' => 'my_db'
));
```

## Working with models ##

### Creating models ###

When looking to create your doctrine models, you have two choices: you can
have them follow your custom class hierarchy (or none at all), or you could
have them extend from the `BaseEntity` class provided by this library. The
advantage of choosing the later is that your models will have lithium's
validation support, and can be better integrated with the custom adapters 
provided by this library (such as for session management or for authorization.)

Let us create a `User` model. Following doctrine's [basic mapping guide] 
[doctrine-mapping-guide] we'll use annotations to define the properties, and
we will also include lithium validation rules (that's why we are choosing to 
extend this model from `BaseEntity`):

```php
<?php
namespace app\models;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Table;
use lithium\security\Password;

/**
 * @Entity
 * @Table(name="users")
 */
class User extends \li3_doctrine2\models\BaseEntity {
    /**
     * @Id
     * @GeneratedValue
     * @Column(type="integer")
     */
    private $id;

    /**
     * @Column(type="string",unique=true)
     */
    private $email;

    /**
     * @Column(type="text")
     */
    private $password;

    /**
     * @Column(type="string")
     */
    private $name;

    /**
     * Validation rules
     */
    protected $validates = array(
        'email' => array(
            'required' => array('notEmpty', 'message' => 'Email is required'),
            'valid' => array('email', 'message' => 'You must specify a valid email address', 'skipEmpty' => true)
        ),
        'password' => array('notEmpty', 'message' => 'Password must not be blank'),
        'name' => array('notEmpty', 'message' => 'Please provide your full name')

    );

    public function getId() {
        return $this->id;
    }

    public function getEmail() {
        return $this->email;
    }

    public function setEmail($email) {
        $this->email = $email;
    }

    public function getPassword() {
        return $this->password;
    }

    public function setPassword($password) {
        $this->password = !empty($password) ? Password::hash($password) : null;
    }

    public function getName() {
        return $this->name;
    }

    public function setName($name) {
        $this->name = $name;
    }
}
?>
```

You should note that if you make your model properties private, each property
*should have* a getter and a setter method, otherwise validation and other
features provided by `BaseEntity` won't work.

### Using the Doctrine shell to generate the schema ###

Once you have your model(s) created, you can use doctrine's shell to generate
the schema. li3\_doctrine2 offers a wrapper for doctrine's shell that
reutilizes lithium's connection details. To run the access the core directory 
of your application and do:

```bash
$ app/libraries/li3_doctrine2/bin/doctrine
```

That will give you all the available commands. For example, to get the SQL
you should run to create the schema for your models, do:

```bash
$ app/libraries/li3_doctrine2/bin/doctrine orm:schema-tool:create --dump-sql
```

which will give an output similar to the following:

```sql
CREATE TABLE users (
    id INT AUTO_INCREMENT NOT NULL, 
    email VARCHAR(255) NOT NULL, 
    password LONGTEXT NOT NULL, 
    name VARCHAR(255) NOT NULL, 
    UNIQUE INDEX UNIQ_1483A5E9E7927C74 (email), 
    PRIMARY KEY(id)
) ENGINE = InnoDB
```

### Getting the entity manager ###

Doctrine's `EntityManager` is the way we have to interact with the underlying
database, which means we'll always need to obtain it. You can do so by
running the following code (change `default` to the name of your connection
as defined in `app/config/connections.php`):

```php
$em = \lithium\data\Connections::get('default')->getEntityManager();
```

If your models extend from `BaseEntity`, then all of them have a static method
named `getEntityManager()` (which uses a static property inherited from
`BaseEntity` named `$connectionName` to figure out what connection to use):

```php
$em = User::getEntityManager();
```

### Fetching records ###

Once you have the entity manager, you can fetch a user with ID 1 (notice how
we use the fully qualified class name for the model) using the entity
manager:

```php
$user = $em->find('app\models\User', 1);
```

or using model repositories:

```php
$user = $em->getRepository('app\models\User')->findOneById(1);
```

If you want to find out more about querying models with Doctrine, go through
its [Querying guide] [doctrine-querying-guide].

### Creating/Updating/Deleting records ###

Records are persisted (or removed) though the entity manager, as shown in
Doctrine's [Persisting guide] [doctrine-persisting-guide].

One thing to note is that if your models extend from `BaseEntity`, you have
validation rules defined for them, and the data you provide does not validate,
persisting it will throw a `ValidateException` (the following example uses
the `User` model we defined earlier):

```php
$user = new User();
$user->setName('John Doe');
$user->setEmail('bademail@');

try {
    $em->persist($user);
    $em->flush();
} catch(\li3_doctrine2\models\ValidateException $e) {
    echo $e->getMessage();
}
```

You should also note that `BaseEntity` provides a method named `set()` which
comes very handy if the user data is to be populated from a form submission.
If so, the above code could be rewritten as:

```php
$user = new User();
$user->set($this->request->data);

try {
    $em->persist($user);
    $em->flush();
} catch(\li3_doctrine2\models\ValidateException $e) {
    echo $e->getMessage();
}
```

In this last example, if lithium's form helper is bound to the record instance,
it will properly show validation errors. The following view code uses the
`$user` variable from the example above to bind the form to its validation
errors:

```php
<?php echo $this->form->create(isset($user) ? $user : null); ?>
    <?php echo $this->form->field('email'); ?>
    <?php echo $this->form->field('password', array('type' => 'password')); ?>
    <?php echo $this->form->field('name'); ?>
    <?php echo $this->form->submit('Signup'); ?>
<?php echo $this->form->end(); ?>
```

# Extensions #

li3\_doctrine2 also offers a set of extensions to integrate different parts
of your application with your doctrine models.

## Session ##

Some installations require session data to be stored on a centralized location.
While there are powerful, storage-centric solutions for session storage, using
the database is still a popular choice.

If you wish to store your session data on the database, using Doctrine models,
then you will need to use li3\_doctrine2's session adapter. You start by
creating the model that the library will use to represent a session record.
For example, create a file named `Session.php` and place it in your
`app/models` folder with the following contents:

```php
<?php
namespace app\models;

/**
 * @Entity
 * @Table(name="sessions")
 */
class Session extends \li3_doctrine2\models\BaseSession {
}
?>
```

We are extending from `BaseSession` since it provides us with the needed
methods the session adapter will expect it to have. Remember to create the
schema for this model.

The final step is configuring the session. Edit your 
`app/config/bootstrap/session.php` file and use the following to configure the
session:

```php
Session::config(array(
    'default' => array(
        'adapter' => 'li3_doctrine2\extensions\adapter\session\Entity',
        'model' => 'app\models\Session'
    )
));
```

If you wish to override session INI settings, use the `ini` setting. For 
example, if you wish your session data to be valid across all subdomains, 
replace the session definition with the following:

```php
$host = $_SERVER['HTTP_HOST'];
if (strpos($host, '.') !== false) {
    $host = preg_replace('/^.*?([^\.]+\.[^\.]+)$/', '\\1', $host);
}
Session::config(array(
    'default' => array(
        'adapter' => 'li3_doctrine2\extensions\adapter\session\Entity',
        'model' => 'app\models\Session',
        'ini' => array(
            'cookie_domain' => '.' . $host
        )
    )
));
```

## Authentication ##

Even when you could easily build your own authentication library, using
[lithium's implementation] [lithium-authentication] is highly recommended. If
you wish to go this route, you'll need li3\_doctrine's Form adapter for
authentication, since it allows it to interact with Doctrine models.

The model you wish to use should extend from `BaseEntity` (you could still make
it work without extending from it if you implement the needed methods). We will
use the `User` model we created earlier.

Once you have your model, you need to configure `Auth`. Edit your
`app/config/bootstrap/session.php` and add the following to the end:

```php
use lithium\security\Auth;

Auth::config(array(
    'default' => array(
        'adapter' => 'li3_doctrine2\extensions\adapter\security\auth\Form',
        'model' => 'app\models\User',
        'fields' => array('email', 'password')
    )
));
```

Once this is done, you can use `Auth` as usual.

# Integrating Doctrine libraries #

In this section I'll cover some of the doctrine extension libraries out there,
and how to integrate them with li3_\doctrine2.

## DoctrineExtensions ##

If there is one tool I would recommend you checkout for your Doctrine models,
that would be [DoctrineExtensions] [DoctrineExtensions]. It provides with a set
of behavioral extensions to the Doctrine core that will simplify your
development.

To use DoctrineExtensions, you should first add it as GIT submodule. To do so, 
switch to the core directory holding your lithium application, and do:

```bash
$ git submodule add https://github.com/l3pp4rd/DoctrineExtensions.git app/libraries/_source/DoctrineExtensions
```

Next you would use your connection configuration (in `app/config/connections.php`)
to configure Doctrine with your desired behaviors. For example, if you wish
to use Timestampable and Sluggable, you would first add the library in
`app/config/libraries.php`:

```php
Libraries::add('Gedmo', array(
    'path' => LITHIUM_APP_PATH . '/libraries/_source/DoctrineExtensions/lib/Gedmo'
));
```

And then you would filter the `createEntityManager` method in the `Doctrine`
datasource to add the behaviors. Edit your `app/config/connections.php` file
and add the following right below the connection definition:

```php
Connections::get('default')->applyFilter('createEntityManager',
    function($self, $params, $chain) {
        $params['eventManager']->addEventSubscriber(
            new \Gedmo\Timestampable\TimestampableListener()
        );
        $params['eventManager']->addEventSubscriber(
            new \Gedmo\Sluggable\SluggableListener()
        );
        return $chain->next($self, $params, $chain);
    }
);
```

[lithium]: http://lithify.me
[doctrine2]: http://www.doctrine-project.org
[license]: http://www.opensource.org/licenses/bsd-license.php
[DoctrineExtensions]: https://github.com/l3pp4rd/DoctrineExtensions
[doctrine-mapping-guide]: http://www.doctrine-project.org/docs/orm/2.1/en/reference/basic-mapping.html
[doctrine-querying-guide]: http://www.doctrine-project.org/docs/orm/2.1/en/reference/working-with-objects.html#querying
[doctrine-persisting-guide]: http://www.doctrine-project.org/docs/orm/2.1/en/reference/working-with-objects.html#persisting-entities
[lithium-authentication]: http://lithify.me/docs/manual/auth/simple-authentication.wiki

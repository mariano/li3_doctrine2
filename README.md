li3\_doctrine2 offers integration between [the most RAD PHP framework] [lithium]
and possibly the best PHP 5.3 ORM out there: [Doctrine2] [doctrine2]

# License #

li3\_doctrine2 is released under the [MIT License] [license].

# Installation #

Install [Composer] [composer] if you didn't already. Then add li3\_doctrine2 as
a required package (together with Doctrine and migrations):

```json
{
	"config": {
		"vendor-dir": "libraries"
	},
	"require": {
		"doctrine/orm": ">=2.1",
		"doctrine/migrations": "dev-master",
		"mariano/li3_doctrine2": "dev-master"
	}
}
```

Finally, tell composer to install these packages:

```bash
$ composer install
```

You will now need to ensure that Composer's autoload file is loaded so all
vendor classes (such as Doctrine) can be loaded, and then load the li3\_doctrine2
library. Place the following at the end of your 
`app/config/bootstrap/libraries.php` file:

```php
require_once(LITHIUM_LIBRARY_PATH . '/autoload.php');

Libraries::add('li3_doctrine2', [
	'path' => LITHIUM_LIBRARY_PATH . '/mariano/li3_doctrine2'
]);
```

# Usage #

## Defining a connection ##

Setting up a connection with li3\_doctrine2 is easy. All you need to do is
add the following to your `app/config/bootstrap/connections.php` file (make
sure to edit the settings to match your host, without altering the `type`
setting):

```php
Connections::add('default', [
	'type' => 'Doctrine',
	'driver' => 'pdo_mysql',
	'host' => 'localhost',
	'user' => 'root',
	'password' => 'password',
	'dbname' => 'my_db'
]);
```

### Working with master-slave connections ###

Thanks to Doctrine, master/slave connection queries can be done quite easy. All
you have to do is slightly change your connection definition so you can use
the `MasterSlaveConnection` wrapper class, and instead of simply specifying a 
single server, you give the details for the master server, and each of the 
slave servers. Example:

```php
Connections::add('default', [
	'type' => 'Doctrine',
	'driver' => 'pdo_mysql',
	'wrapperClass' => 'Doctrine\DBAL\Connections\MasterSlaveConnection',
    'master' => [
		'host' => 'master.example.com',
		'user' => 'root',
		'password' => 'password',
		'dbname' => 'my_db'
	],
    'slaves' => [
		[
			'host' => 'slave1.example.com',
			'user' => 'root',
			'password' => 'password',
			'dbname' => 'my_db'
		],
		[
			'host' => 'slave2.example.com',
			'user' => 'root',
			'password' => 'password',
			'dbname' => 'my_db'
		]
    ]
]);
```

## Working with models ##

### Creating models ###

When looking to create your doctrine models, you have two choices: you can
have them follow your custom class hierarchy (or none at all), or you could
have them extend from the `BaseEntity` class provided by this library. The
advantage of choosing the later is that your models will have lithium's
validation support, and can be better integrated with the custom adapters 
provided by this library (such as for session management or for authorization.)

> If you still want validation support but do not wish to extend `BaseEntity`
> your models should implement the `li3_doctrine2\models\IModel` interface.

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
	protected $validates = [
		'email' => [
			'required' => ['notEmpty', 'message' => 'Email is required'],
			'valid' => ['email', 'message' => 'You must specify a valid email address', 'skipEmpty' => true]
		],
		'password' => ['notEmpty', 'message' => 'Password must not be blank'],
		'name' => ['notEmpty', 'message' => 'Please provide your full name']
	];

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
**must have** a getter and a setter method, otherwise validation and other
features provided by `BaseEntity` won't work.

### Using the Doctrine shell to generate the schema ###

Once you have your model(s) created, you can use doctrine's shell to generate
the schema. li3\_doctrine2 offers a wrapper for doctrine's shell that
reutilizes lithium's connection details. To run the access the core directory 
of your application and do:

```bash
$ libraries/li3_doctrine2/bin/doctrine
```

That will give you all the available commands. For example, to get the SQL
you should run to create the schema for your models, do:

```bash
$ libraries/li3_doctrine2/bin/doctrine orm:schema-tool:create --dump-sql
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

`BaseEntity` also offers a `getRepository()` method which will return the
repository for the model (see the section *Fetching record* below.)

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

If your model extends from `BaseEntity`, then the above could be retwritten
as:

```php
$user = User::getRepository()->findOneById(1);
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

> You may notice that we send a list of field names as the second argument to
> the `set()` method. More about this in the section *Field whitelist in 
> BaseEntity::set()*

```php
$user = new User();
$user->set($this->request->data, ['name', 'email']);

try {
	$em->persist($user);
	$em->flush();
} catch(\li3_doctrine2\models\ValidateException $e) {
	echo $e->getMessage();
}
```

In this last example, if lithium's Form helper is bound to the record instance,
it will properly show validation errors. The following view code uses the
`$user` variable from the example above to bind the form to its validation
errors:

```php
<?php echo $this->form->create(isset($user) ? $user : null); ?>
	<?php echo $this->form->field('email'); ?>
	<?php echo $this->form->field('password', ['type' => 'password']); ?>
	<?php echo $this->form->field('name'); ?>
	<?php echo $this->form->submit('Signup'); ?>
<?php echo $this->form->end(); ?>
```

#### Field whitelist in BaseEntity::set() ####

In the preceeding example, we shown the `set()` method, inherited from
`BaseEntity`, as a convenient way to populate entity fields as that come from of
a form submission. As part of the shown `set()` usage, you may have noticed a
list of fields passed on the second argument:

```php
$user->set($this->request->data, ['name', 'email']);
```

This argument is a whitelist of fields that specifies which fields that are
part of the first argument (`$this->request->data` in this case) are allowed to
be set on the entity.

I've struggled against not including the whitelist argument with the idea that
security should be enforced in the application logic. However, recent events
in the [rails arena] [rails-fiasco] convinced me that my original intention
of forcing a whitelist has more advantages than disadvantages.

In any case, if you wish to avoid setting a whitelist, you can pass an empty
array on the second argument, and `false` to the third argument. So the
example above would be changed to:

```php
$user->set($this->request->data, [], false)
```

# Extensions #

li3\_doctrine2 also offers a set of extensions to integrate different parts
of your lithium application with your doctrine models.

## Validators ##

For convenience, li3\_doctrine2 adds some custom validators that require
interaction with Doctrine2 entities. To use these validators, you will have
to set the `validators` option to `true` when adding the library:

```php
Libraries::add('li3_doctrine2', [
	'path' => LITHIUM_LIBRARY_PATH . '/mariano/li3_doctrine2',
	'validators' => true
]);
```

### Unique validator ###

This validator will fail if the value for the given field already exists for
the model we are validating. To add this validator to a field named `email`
for your `User` model, you'd add the following expression to the model's
`$validates` property (you'll see we also add other lithium's built in rules
for informational purposes only):

```php
'email' => [
	'required' => ['notEmpty', 'message' => 'Email is required'],
	'valid' => ['email', 'message' => 'You must specify a valid email address, 'skipEmpty' => true],
	'unique' => ['unique', 'message' => 'This email is already being used for another account, 'skipEmpty' => true]
]
```

The `unique` validator accepts a handful of options:

* `conditions`: Extra conditions that will be added to the default condition 
(in the above example the default condition would be `email => value`, where
value is the value given when submitting the form). Defaults to: `[]`.
* `getEntityManager`: The method defined in the model that is used to
obtain the model's entity manager. If your model extends from `BaseEntity`, the
default will work just fine. If you don't want to extend from `BaseEntity`,
you have to either implement this method, or use the `connection` option.
Defaults to: `getEntityManager`
* `connection`: If the method defined in `getEntityManager` does not exist
in the model, or is empty, a connection name is needed to obtain the model's 
entity manager. Defaults to: `default`
* `checkPrimaryKey`: If set to `true`, the model's identifier (its primary key
value) will be used to make sure than when looking for uniqueness, the same
record does not trigger a failed validation. Defaults to: `true`

## Session ##

Some installations require session data to be stored on a centralized location.
While there are powerful, storage-centric solutions for session storage, using
the database is still a popular choice.

If you wish to store your session data on the database using Doctrine models,
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
methods the session adapter will expect it to have.

> If you still want to use the session adapter with your own Doctrine models,
> but do not wish to extend `BaseSession`, then your session model should
> implement the `li3_doctrine2\models\ISession` interface. You should also
> note that if you do not pass the `entityManager` setting to the session
> configuration, then your session model should implement a static method
> named `getEntityManager()` that should return Doctrine's entity manager
> for the session model. This method is not part of the interface signature
> because it is optional, and is only used if you don't set the
> `entityManager` session configuration setting.

Once the model is created, create its database table using the doctrine
console. Finally, edit your `app/config/bootstrap/session.php` file and use the 
following to configure the session:

```php
Session::config([
	'default' => [
		'adapter' => 'li3_doctrine2\extensions\adapter\session\Entity',
		'model' => 'app\models\Session'
	]
]);
```

If you wish to override session INI settings, use the `ini` setting. For 
example, if you wish your session data to be valid across all subdomains, 
replace the session definition with the following:

```php
$host = $_SERVER['HTTP_HOST'];
if (strpos($host, '.') !== false) {
	$host = preg_replace('/^.*?([^\.]+\.[^\.]+)$/', '\\1', $host);
}
Session::config([
	'default' => [
		'adapter' => 'li3_doctrine2\extensions\adapter\session\Entity',
		'model' => 'app\models\Session',
		'ini' => ['cookie_domain' => '.' . $host]
	]
]);
```

## Authentication ##

Even when you could easily build your own authentication library, using
[lithium's implementation] [lithium-authentication] is highly recommended. If
you wish to go this route, you'll need li3\_doctrine's Form adapter for
authentication, since it allows it to interact with Doctrine models. The model 
you wish to use should extend from `BaseEntity`.

> If you still want to use the Form adapter but do not wish to extend
> `BaseEntity`, then your model should implement the 
> `li3_doctrine2\models\IUser` interface. You should also note that if you do 
> not pass the `entityManager` setting to the auth configuration, then your 
> model should implement a static method named `getEntityManager()` that 
> should return Doctrine's entity manager for the model. This method is not 
> part of the interface signature because it is optional, and is only used if
> you don't set the `entityManager` session configuration setting.

Once you have your model, you need to configure `Auth`. Edit your
`app/config/bootstrap/session.php` and add the following to the end:

```php
use lithium\security\Auth;

Auth::config([
	'default' => [
		'adapter' => 'li3_doctrine2\extensions\adapter\security\auth\Form',
		'model' => 'app\models\User',
		'fields' => ['email', 'password']
	]
]);
```

Once this is done, you can use `Auth` as usual.

# Integrating libraries #

In this section I'll cover some of the doctrine extension libraries out there,
how to integrate them with li3\_doctrine2, and how to let li3\_doctrine2 work 
with other lithium libraries that might be of use for your application.

## DoctrineExtensions ##

If there is one tool I would recommend you checkout for your Doctrine models,
that would be [DoctrineExtensions] [DoctrineExtensions]. It provides with a set
of behavioral extensions to the Doctrine core that will simplify your
development.

To use DoctrineExtensions, you should first add it as GIT submodule. To do so, 
switch to the core directory holding your lithium application, and do:

```bash
$ git submodule add https://github.com/l3pp4rd/DoctrineExtensions.git libraries/_source/DoctrineExtensions
```

You would then use your connection configuration (in 
`app/config/connections.php`) to configure Doctrine with your desired behaviors. 
For example, if you wish to use Timestampable and Sluggable, you would first add 
the library in `app/config/libraries.php`:

```php
Libraries::add('Gedmo', [
	'path' => LITHIUM_LIBRARY_PATH . '/_source/DoctrineExtensions/lib/Gedmo'
]);
```

And then you would filter the `createEntityManager()` method in the `Doctrine`
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

## Li3Perf ##

[li3_perf] [li3_perf] is a handy utility that you should use (only when the
development environment is activated, though) to keep track of bottlenecks,
and potential performance problems.

One of the features it offers is the ability to show all the database queries
that were executed as part of a request. In order to use that functionality 
with li3\_doctrine2, a little work has to be done. Fortunately, it's quite 
easy.

Create a file named `Li3PerfSQLLogger.php` and place it in your 
`app/libraries/_source` folder with the following contents:

```php
<?php
namespace app\libraries\_source;

use Doctrine\DBAL\Logging\SQLLogger;
use li3_perf\extensions\util\Data;

class Li3PerfSQLLogger implements SQLLogger {
	protected $query;
	protected $start;

	public function startQuery($sql, array $params = null, array $types = null) {
		$this->start = microtime(true);
		$this->query = compact('sql', 'params', 'types');
	}

	public function stopQuery() {
		$ellapsed = (microtime(true) - $this->start) * 1000;
		Data::append('queries', [array_merge(
			['explain' => ['millis' => $ellapsed]],
			$this->query
		)]);
	}
}

?>
```

Now, we need to filter the `createEntityManager()` method of the `Doctrine`
datasource. Edit your `app/config/connections.php` file and add the following 
right below the connection definition:

```php
Connections::get('default')->applyFilter('createEntityManager',
	function($self, $params, $chain) {
		if (\lithium\core\Libraries::get('li3_perf')) {
			$params['configuration']->setSQLLogger(
				new \app\libraries\_source\Li3PerfSQLLogger()
			);
		}
		return $chain->next($self, $params, $chain);
	}
);
```

Notice how we are only using the logger we created if the li3\_perf library
is activated. You should now see your queries on the performance toolbar.

[lithium]: http://lithify.me
[doctrine2]: http://www.doctrine-project.org
[license]: http://www.opensource.org/licenses/mit-license.php
[composer]: http://getcomposer.org
[DoctrineExtensions]: https://github.com/l3pp4rd/DoctrineExtensions
[doctrine-mapping-guide]: http://www.doctrine-project.org/docs/orm/2.1/en/reference/basic-mapping.html
[doctrine-querying-guide]: http://www.doctrine-project.org/docs/orm/2.1/en/reference/working-with-objects.html#querying
[doctrine-persisting-guide]: http://www.doctrine-project.org/docs/orm/2.1/en/reference/working-with-objects.html#persisting-entities
[lithium-authentication]: http://lithify.me/docs/manual/auth/simple-authentication.wiki
[li3_perf]: https://github.com/tmaiaroto/li3_perf
[rails-fiasco]: http://chrisacky.posterous.com/github-you-have-let-us-all-down

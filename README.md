
#DumbledORM
A PHP Novelty ORM

##Requirements:
* PHP 5.3+
* All tables must have a single (not composite) primary key called `id` that is an auto-incrementing integer
* All foreign keys must follow the convention of `table_name_id`
* All meta tables must be:
 * named `table_name_meta` 
 * have a foreign key to the corresponding parent table
 * have a column called `key` 
 * have a column called `val`


##Setup:

1. Download/clone DumbledORM
2. Ensure that your config.php is has the correct host, user, pass, etc.
3. When you have set up your database or have made a change, go to the command line and type `php -a` and enter the following commands:

		require('config.php');
		require('dumbledorm.php');
		Builder::generateBase();

4. Add the following lines to your code:

		require('config.php');
		require('dumbledorm.php');
		require('./model/base.php');

That's it.  There's an autoloader built in for the generated classes.

###Builder configuration

`Builder::generateBase()` will always overwrite `base.php` but never any generated classes.

If you want to prefix the classes that are generated:

	Builder::generateBase('myprefix');

If you want to put the generated classes in a different directory than the default "model":

	Builder::generateBase(null,'mymodeldir/model');

###Testing

DumbledORM includes a simple test script.  You can run it from the command line.  Just modify the DbConfig in the test script to your params.

	php test.php

##Usage

####Create a new record
	$user = new User(array(
	  'name' => 'Jason', 
	  'email' => 'jasonmoo@me.com', 
	));
	$user->save();

####Load an existing record and modify it
	$user = new User(13);  // load record with id 13
	$user->setName('Jason')->save();

####Find a single record and delete it
	User::one(array('name' => 'Jason'))->delete();

####Find all records matching a query and modify them
	// applies setLocation and save to the entire set
	PhoneNumber::select('`number` like "607%"')
	  ->setLocation('Ithaca, NY')
	  ->save();

####Find all records matching a query and access a single record by id
	$users = User::select('`name` like ?',$val);
	echo $users[13]->getId(); // 13

####Find all records matching a query and iterate over them
	foreach (User::select('`name` like ?',$val) as $id => $user) {
	  echo $user->getName().": $id\n";  // Jason: 13
	}

####Create a related record
	$user->create(new PhoneNumber(array(
	  'type' => 'home', 
	  'number' => '607-333-2840', 
	)))->save();

####Fetch a related record and modify it
	// fetches a single record only
	$user->getPhoneNumber()->setType('work')->save();

####Fetch all related records and iterate over them.	
	// boolean true causes all related records to be fetched
	foreach ($user->getPhoneNumber(true) as $ph) {
	  echo $ph->getType().': '.$ph->getNumber();
	}

####Fetch all related records matching a query and modify them
	$user->getPhoneNumber('`type` = ?',$type)
	  ->setType($new_type)
	  ->save()

####Set/Get metadata for a record
	// set a batch
	$user->addMeta(array(
	  'background' => 'blue', 
	  'last_page' => '/', 
	));
	// set a single
	$user->setMeta('background','blue');
	// get a single
	$user->getMeta('background'); // blue
	// metadata saved automatically
	$user->save();  
Known bugs:

- When you get this error:
  Uncaught PHP Exception Drupal\Core\Database\ConnectionNotDefinedException: "The specified database connection is not defined: migrate"
  Disable the migrate_drupal module.
  This is a bug in migrate/src/Plugin/migrate/source/SqlBase.php, where it defines migrate as default source database.
  For some reason Drupal6_fields needs this. Disabling migrate_drupal gets around the issue.
  You can also add this line in settings.php (but might not work on sitefarm):
  $databases['migrate']['default'] = $databases['default']['default'];
  
- PHP Fatal error:  Call to a member function get() on boolean in ...
  This will trigger when you try to create a migration using a template, but the template hasnt been loaded properly. Resetting the cache
  using usually fixes this. Check Drupal\sitefarm_migrate::createMigration for a way to get around it


- Migrate stores its config in TWO places, namely in:
 - plugin.manager.config_entity_migration
 - plugin.manager.migration
 This caused me an enormous amount of confusion. Namely after creating a migration, it is NOT stored in both places. You have to
 create the instance in where you created it, create a new intance of the other type, and load it in there too to properly save it.
 
 Edit, Migrate fixed this issue, everything is stored into plugin.manager.migration now and config_entity_migration has been removed.
# SiteFarm Migrate

*WARNING: This module WILL NOT WORK with your Drupal site.*

The [Bad Judgement Module](https://www.drupal.org/project/bad_judgement) is included as a dependency to reinforce this warning. 

This is an example module of how UC Davis SiteFarm implements its migration tools. It is meant to help you create your own migration toolkit. You will need to modify this code pretty heavily before it will be useful to you. After you have done that, edit the sitefarm_migrate.info.yml file to remove the dependency on bad_judgement.

That being said, if you are using [SiteFarm Seed](https://github.com/ucdavis/sitefarm_seed) it is possible that many of your content architecture is very similar, so the modification you will need to perform may be minimal.
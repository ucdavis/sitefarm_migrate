Sitefarm People Data Migration using CSV
========================================

Create the CSV using either a text editor or using Excel and saving to CSV, making sure the field delimiter is a comma.

Here is a template example for valid CSV people content:

```
id,first_name,middle_initial,last_name,name_prefix,credentials,position_title,unit,person_type,email,phone,website,Office hours,Office location,Country,Company,Address,Address2,City,Zip,State,Bio Summary,Bio,hide_from_dir,featured,tags,documents,portrait_image,url,cas
1,Person,O,One,,cred1,Programmer 4,IET,Faculty,pone@ucdavis.edu,(530)555-2222,'[First Website](http://firstwebsite.com/test&amp;test2#anchor)|[Second Website](http://secondwebsite.com)|http://thirdwebsite.com',10a-4p,My Location,US,Comp1,999 Jsaf,suite2,Davis,95616,CA,"Summary Test",Professional bio,,1,retired|IET,/profiles/sitefarm/tests/files/test.pdf|/profiles/sitefarm/tests/files/test 2.pdf,/profiles/sitefarm/tests/files/portrait.png,/old_user/123,pone
2,Palm,M,Two,cred2,,Supervisor I,IET,Stafftest,ptwo@ucdavis.edu|ptwo@tesla.net,(530)752-5555|(916)555-5529,'http://firstwebsite.com|[Sample News](news/sample-article)',9a-5p,My Awesome Location,US,comp2,443 K Ste, ,Sacramento,95818,CA,"Bio Summary","<b>Very professional bio</b>",,0,"IET|tag with spaces",,,/users/124,ptwo
3,John,D,Tree,cred3,prefix,Programmer 3, IET,Researchertest,jthree@ucdavis.edu|john@gmail.com,(530)115-2555,'news/sample-article',12n-6p,My Location,US,comp3,444 J street, ,Sacramento,95616,CA,"Summary for Bio",Extremely professional bio,1,0,tag1|tag2|tag3,,"http://via.placeholder.com/2000x700.png",/old_user/jtree,jtree
```

[/profiles/sitefarm/tests/files/people.csv](Download the example file here)

The fields required will have a (*) next to the field name:

| Field Name	|  Format |
| ------------- | ------------- |
| Id*           | number |
| first_name*	| text |
| middle_initial| text |
| last_name*	| text |
| name_prefix   | text |
| credentials	| text |
| position_title| text |
| unit          | text, can have multiple values |
| person_type   | text |
| email*        | email format |
| phone         | can have multiple phone numbers - phone number format  (flexible) |
| websites      | Format [title](url), separate with pipe, no spaces: "[Google](https://google.com)|[Yahoo](https://yahoo.com)" |
| Office Hours  | text |
| location      | text |
| country*      | must be 2 letter country code (US,CA, etc) |
| company       | text |
| address1      | text |
| address2      | text |
| city          | text |
| zipcode       | numbers but can have a dash for zip+4 |
| state         | 2 letter state code |
| bio_summary   | text |
| bio           | text, make sure to use double quotes to encapsulate the bio |
| hide_from_dir | bool, true or 1 to hide this person from the listings |
| featured      | bool, true or 1 to make this person featured |
| tags          | text, separated by "|" |
| documents     | Absolute or relative url to a document, multiple separated by "|" |
| portrait_image| Absolute or relative url to an image |
| url           | relative url to old profile (i.e. /users/1234). A redirect will be created to the new profile url |
| cas           | user cas ID. When provided with name and email, user will be created and will be able to log in using this cas id |


Important CSV Pro-tips
----------------------

* Must have a header in file
* Must be saved with UTF-8 encoding
* For any text fields, use a double quote to use commas within content. For example: "Hello, my name is Tom"
* For fields that allow multiple values such as website, unit or phone, use a pipe to separate the values.
* If any value is not being used, simply create a placeholder for it. Every value must have a spot.



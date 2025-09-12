<table width="100%">
	<tr>
		<td align="left" width="100%" colspan="2">
			<strong>AWS SES wp_mail() drop-in</strong><br />
			Use AWS SES to send your WordPress emails. Easily.
		</td>
	</tr>
	<tr>
		<td>
			A <strong><a href="https://hmn.md/">Human Made</a></strong> project. Maintained by @joehoyle.
		</td>
		<td align="center">
			<img src="https://hmn.md/content/themes/hmnmd/assets/images/hm-logo.svg" width="100" />
		</td>
	</tr>
</table>

AWS SES is a very simple UI-less plugin for sending `wp_mail()`s email via AWS SES.

Installation
==========

The ideal approach for using this plugin is to install it via composer at the root of your project. This can prevent multiple versions of the Amazon SDK from being installed within your codebase. 

```
composer require humanmade/aws-ses-wp-mail
```

Otherwise, clone the plugin to  `/wp-content/plugins` and then run `composer install` within the resulting directory. 

Configuration
==========

Once installed, add the following constants to your `wp-config.php`:

```PHP
define( 'AWS_SES_WP_MAIL_REGION', 'us-east-1' );
define( 'AWS_SES_WP_MAIL_KEY', '' );
define( 'AWS_SES_WP_MAIL_SECRET', '' );
define( 'AWS_SES_WP_MAIL_CONFIG_SET', '' );
```

If you plan to use IAM instance profiles to protect your AWS credentials on disk you'll need the following configuration instead:

```PHP
define('AWS_SES_WP_MAIL_REGION', 'us-east-1');
define('AWS_SES_WP_MAIL_USE_INSTANCE_PROFILE', true);
```


The next thing that you should do is to verify your sending domain for SES. You can do this via the AWS Console, which will allow you to automatically set headers if your DNS is hosted on Route 53. Alternatively, you can get the required DNS records by running:

```
wp aws-ses verify-sending-domain
```

Once you have verified your sending domain, you are all good to go!

**Note:** If you have not used SES in production previously, you need to apply to [move out of the Amazon SES sandbox](http://docs.aws.amazon.com/ses/latest/DeveloperGuide/request-production-access.html).

### Configuration Sets

To better track your mail activity for monitoring or statistics you can use the configuration sets. To enable it you will first need to create your Configuration Set on AWS SES Console and add the configuration set name as the value to the `AWS_SES_WP_MAIL_CONFIG_SET` constant. 

Detailed information on the setup and usage you find here: https://docs.aws.amazon.com/ses/latest/DeveloperGuide/using-configuration-sets.html

Other Commands
=======

`wp aws-ses send <to> <subject> <message> [--from-email=<email>]`

Send a test email via the command line. Good for testing!

Credits
=======
Created by Human Made for high volume and large-scale sites. We run AWS SES wp_mail() on sites with millions of monthly page views, and thousands of sites.

Written and maintained by [Joe Hoyle](https://github.com/joehoyle). Thanks to all our [contributors](https://github.com/humanmade/S3-Uploads/graphs/contributors).

Interested in joining in on the fun? [Join us, and become human!](https://hmn.md/is/hiring/)

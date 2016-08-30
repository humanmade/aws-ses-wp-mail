<table width="100%">
	<tr>
		<td align="left" width="70">
			<strong>AWS SES wp_mail() drop-in</strong><br />
			Use AWS SES to send your WordPress emails. Easily.
		</td>
		<td align="right" width="20%">

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

Getting Set Up
==========

Once you have `git clone`d the repo, or added it as a Git Submodule, add the following constants to your `wp-config.php`:

```PHP
define( 'AWS_SES_WP_MAIL_REGION', 'us-east-1' );
define( 'AWS_SES_WP_MAIL_KEY', '' );
define( 'AWS_SES_WP_MAIL_SECRET', '' );
```

If you plan to use IAM instance profiles to protect your AWS credentials on disk you'll need the following configuration instead:

```PHP
define('AWS_SES_WP_MAIL_REGION', 'us-east-1');
define('AWS_SES_WP_MAIL_USE_INSTANCE_PROFILE', true);
```


The next thing that you should do is to verify your sending domain for SES:

```
wp aws-ses verify-sending-domain
```

Once you have verified your sending domain, you are all good to go!

Other Commands
=======

`wp aws-ses send <to> <subject> <message> [--from-email=<email>]`

Send a test email via the command line. Good for testing!

Credits
=======
Created by Human Made for high volume and large-scale sites. We run AWS SES wp_mail() on sites with millions of monthly page views, and thousands of sites.

Written and maintained by [Joe Hoyle](https://github.com/joehoyle). Thanks to all our [contributors](https://github.com/humanmade/S3-Uploads/graphs/contributors).

Interested in joining in on the fun? [Join us, and become human!](https://hmn.md/is/hiring/)

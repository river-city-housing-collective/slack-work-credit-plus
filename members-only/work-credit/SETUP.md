# Setting up this reporting system

There are two services required to use this report: GitHub and Airtable. Once both of these are configured, you can publish your own copy of the work credit report website and share the link to members of your cooperative.

While I've attempted to make this as easy to follow as possible, some of the compromises made to keep everything free have made things a bit more complicated than I'd like. Feel free to [shoot me an email](mailto:contact@izzyneuha.us) if you need clarification on anything outlined in this guide.

## Part 1: Airtable

Airtable is a database service that functions similarly to a spreadsheet (like Microsoft Excel or Google Sheets), but stores data like a database (which is more sustainable long-term). Airtable is what we'll use to provide a survey for submitting work credit hours and track that submission data.

1. Sign up for a free Airtable account if you don't have one already: https://airtable.com/signup
    * I would recommend creating two accounts: One with your personal email and one with an email for your cooperative (that multiple people may have access to). Attaching ownership of the database to a shared co-op email reduces the chance of losing access in the future (as members come and go)
2. After logging in, open the example Work Credit Tracking base and click the "Copy base" button in the top left: https://airtable.com/shrmlushhijHOP1SK
    * By default, the new base will retain all the example data. You may want to leave it intact for now and delete it later (after you've tested the report and made sure everything looks functional)
    * A quick way of getting a clean start is to click on the name of the base (at the top of the window) and use the "Duplicate base" option. You can untick the "Duplicate records" option and create a new copy with no data. Just make sure you delete the old one and get the new base ID for your config file (explained in the next section)

### Getting API keys

In order to allow the report to read data from your Airtable base, you will need to supply it with an API key and a base ID. We will add these keys to our `config.json` file later on.

**WARNING:** Anyone with your API key will be able to read and **write** data on any bases your account has permission to. It is **HIGHLY** recommended that you create an additional (potentially third, if you made both a personal and a co-op account as I recommended above) Airtable account that has **read-only** access to your Work Credit Tracking base and nothing else.

Please refer to the [Notes on security (or lack thereof)](#notes-on-security-or-lack-therof) section for more info about this.

1. While signed into either your personal account or your co-op account, grant the "API only" account **read-only** access to the Work Credit Tracking base (this can be done via the "Share" button near the top right of the table)
2. Click your avatar icon in the top right of the page and click "Account"
3. In the API section, click the "Generate API key" button. It will be hidden for [security reasons](#notes-on-security-or-lack-therof) but you can click it to view and copy. Keep this page open or paste your API key in a safe location so we can reference it later on
4. Click the "Airtable API" link just above your API key
5. Click on your Work Credit Tracking base
6. Find the sentence that says "The ID of this base is ______________" and copy the string of characters (should start with "app"). This is your base ID. Again, you'll need this for the next step so store it somewhere safe or just keep this page open for now
7. Back inside your Work Credit Tracking base, click the "Reported Time" tab (if not open already) and click on "Grid view" just below it
8. Click the "Work Credit Form" option from the list of views. This is the survey that members will use to submit their work credit hours
9. Click the "Share form" button and copy the last part of the private link (airtable.com/______________). This is your unique survey hash, which we'll include in the report config later to link back to this survey

That's all the setup we need to do in Airtable, at least for now. This guide won't go into extensive detail about how to use Airtable, but if you want to learn more, check out https://support.airtable.com/.

## Part 2: GitHub

GitHub is a service for maintaing repositories of source code that also offers a feature in which you can easily generate a website from a repository.

In order to use your own unique version of the work credit report (which retrieves data from your unique copy of the Work Credit Tracking base in Airtable), you'll need to make your own copy of the code and fill in a few blanks (the API key and base ID we grabbed earlier).

1. Create a new GitHub repository using this template: https://github.com/izneuhaus/work-credit-report/generate
    * You will need to sign up for a GitHub account if you don't have one already
    * You may also want to [create an organization account](https://help.github.com/en/articles/creating-a-new-organization-from-scratch) to host the code under (rather than attaching it to someone's personal account). You can learn more about GitHub organizations here: https://help.github.com/en/articles/about-organizations
2. On the main page of your new repository, click the link to the `config.json` file to view its contents
3. Click the pencil icon to open the file for editing. You will need to make the following changes:
    * reportTitle - This is the name that appears as the title of the report. You change it or leave it as is
    * apiKey - Replace this with a personal API key generated by Airtable. You should have this from step 3 in the [Getting API keys](#getting-api-keys) section
    * baseId - Replace this with your unique base ID from Airtable's API documentation. You should have this from step 6 in the [Getting API keys](#getting-api-keys) section
    * surveyHash - Replace this with your unique survey hash. You should have this from step 9 in the [Getting API keys](#getting-api-keys) section
4. Click the "Commit changes" button at the bottom of the page. The report is now configured to connect to your Airtable base
5. Click "Settings" on the navbar at the top of your repository and scroll down to the "GitHub Pages" section
6. Under the "Source" section, select "master branch" from the dropdown menu. The page will reload automatically. Scroll back down to the GitHub Pages section and you should see a message similar to "Your site is ready to be published at ______________". Click this link you should see your new work credit report!
    * By default, this URL will use the "github.io" naming convention. If you own a domain for your co-op, [you can use that instead](https://help.github.com/en/articles/using-a-custom-domain-with-github-pages)

And that's pretty much it! If everything is configured properly, you should now be able to share the link you got from the last step with your members so they can start tracking their time.


## Notes on security (or lack therof)

GitHub Pages is a great solution for hosting a webpage (like this report) because it's free and easy to maintain. However, using a free service like this has its downsides. While most of them are technical things that aren't relevant to the work credit report because it's such a simple tool, there is one limitation that you should be aware of.

As noted above in the [Getting API keys](#getting-api-keys) step, API keys should be handled with care because they have the potential to allow anyone (even without your login information) to modify your Airtable data. As all the source code for this report is clearly visible via GitHub (or via browser tools), so is the `config.json` file where your API keys are clearly exposed. This means anyone that knows what they're doing has the potential to do a lot of damage to your work credit system.

The best way to mitigate this risk is to, as mentioned above, create a separate Airtable account to be used exclusively for generating API tokens. This account should have **read-only** access to your work credit tracking base and nothing else. This ensures that, even if someone did attempt to access your data, they would not be able to modify it directly via the API.

Also worth noting is that there is nothing to stop **anyone** with the URL to your work credit report from reading the report and submitting time via the survey. This has not been an issue for RCHC, but this could be a vector for harassment if, for example, a bad actor wanted to figure out which house and room number a specific person lived in. Just something to be aware of. It may be a good idea to alert your community to this potential risk and offer to de-identify listings (by removing/replacing their name/house/room number) for folks with concerns about this.
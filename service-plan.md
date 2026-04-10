- remove BYOK and use PluginDaddy API service (details below). on the settings page, keep a form that sends an API key to the user email. after sending, the form shows email + api key fields to verify.
- since we're not using BYOK, all API calls to AI will be to plugindaddy.com from now on (keep this site url in a constant so we can change)
- create more folders in the plugin directory
	- website: create a one-page html website in this folder that we'll deploy to server
	- service: the service part plugin, will be hosted on plugindaddy.com. it uses WordPrss + EDD + EDD Recurring payment addon for payment/subscription processing. it'll receive the request and forward to real AI services like DeepSeek, OpenAI or Claude. the additional system and user prompt etc from the main/current plugin can be moved to the service plugin. the main plugin only sends the requirements.


# WIRED Validator

Wordpress post title and exceprt validator developed for WIRED, now available to the public! Validated fields are limited to:

* Post titles and excerpts: Twitter-style validation for minimum and maximum lengths (A counter in each field will indicate how many characters the user has left to type, turning red and preventing save/publish when there are too few or too many)
* Post featured image: Checks for featured image and validates said image meets minimum width (also adds help text to box)
* Profile pages: Minimum bio length checking and checks for user profile image on save for custom WIRED bio fields (can be altered to be defaults if need be)

**NOTE:** The only "hard" validation validation in this plugin is on titles and excerpts that go over length. In other words, titles and excerpts that go over the set length will be trimmed on save. However for all other validation, this plugin simply warns a user if they've not met one of the validation rules. In the case of post titles and excerpts this happens in real time via the counter but will also disable the save/publish button till any invalid fields are resolved. These fields and all other fields output warnings in the messages area of the dashboard (at the top of the screen) after save.

## Adjusting Settings
This plugin adds a custom setting page once enabled which allows you to override the default validation settings, including enabling the plugin feature by feature. You can also alter the min/max validation values. By default these are as follows (min/max):

* Title - 20/80
* Exceprt - 40/140
* User bio - 140
* Featured image width - 1000px
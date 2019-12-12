<p align="center">
  <h1 align="center"> 📷 igFame - Tool for automated Instagram interactions </h1>
  <p align="center">Tooling that <b>automates</b> your instagram media interactions to “farm” Likes, Comments, and Followers on Instagram Implemented in PHP.<p>
</p>

## Table of contents
- [How to install and run igFame](#installation)
  * [Installing igFame](#installation)
  * [Configuring igFame](#configuring-igFame)
  * [Running igFame](#running-igFame)
- [Disclaimer](#disclaimer)
- [Contribution](#contribution)


## **Installation**
```elm
git clone git@github.com:xosad/igFame.git
```

#### Configuring igFame

Start of by editing `config.example.json`:

```
{
     "account": {
     	"username": "janedoe", //Instagram username
     	"password": "janedoe" //Instagram password
     },
     "sleep_delay": 2200, //Sleep delay after looping all tags
     "like_depth_per_user": 3, //How many random images should the bot like from users
     "proxy": "", //proxy eg: 132.123.21.34:6666
     "like_depth_per_tag": 4,  //How many images should the bot like from each tag
     "tags": [
     	"xosad",
     	"igFame", //Tags to like
     	"baransel"
     ],
     "blacklisted_tags": [
     	"testing", //Tags to blacklist
     	"free"
     ],
     "blacklisted_usernames": [
     	"instagram", //Usernames to blacklist
     	"facebook"
     ]
}
```

After editing this rename it to `config.json`.

#### Running igFame

```elm
php bot.php
```

**That's it! 🚀**

---

#### Disclaimer

> **Disclaimer**<a name="disclaimer" />: Please Note that this is a research project. I am by no means responsible for any usage of this tool. Use on your own behalf. I'm also not responsible if your accounts get banned due to extensive use of this tool.


#### Contribution
- Fork this repo.
- Add new features.
- Create a new pull request for this branch.
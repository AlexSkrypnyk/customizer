<p align="center">
  <a href="" rel="noopener">
  <img width=200px height=200px src="https://placehold.jp/000000/ffffff/200x200.png?text=Customizer&css=%7B%22border-radius%22%3A%22%20100px%22%7D" alt="Customizer logo"></a>
</p>

<h1 align="center">Customize the project based on the answers provided by the user.</h1>

<div align="center">

[![GitHub Issues](https://img.shields.io/github/issues/AlexSkrypnyk/customizer.svg)](https://github.com/AlexSkrypnyk/customizer/issues)
[![GitHub Pull Requests](https://img.shields.io/github/issues-pr/AlexSkrypnyk/customizer.svg)](https://github.com/AlexSkrypnyk/customizer/pulls)
[![Test PHP](https://github.com/AlexSkrypnyk/customizer/actions/workflows/test-php.yml/badge.svg)](https://github.com/AlexSkrypnyk/customizer/actions/workflows/test-php.yml)
[![codecov](https://codecov.io/gh/AlexSkrypnyk/customizer/graph/badge.svg?token=7WEB1IXBYT)](https://codecov.io/gh/AlexSkrypnyk/customizer)
![GitHub release (latest by date)](https://img.shields.io/github/v/release/AlexSkrypnyk/customizer)
![LICENSE](https://img.shields.io/github/license/AlexSkrypnyk/customizer)
![Renovate](https://img.shields.io/badge/renovate-enabled-green?logo=renovatebot)

</div>

---

## About

Customize the project based on the answers provided by the user.

This is a single-file Symfony Console Command class designed to be a drop-in
for any scaffold, template, or boilerplate project. It provides a way to ask
questions and process answers to customize user's project.

It is designed to be called during the `composer create-project` command,
including when it is run with the `--no-install` option. It relies only on
the components provided by Composer.

It also supports passing answers as a JSON string via the `--answers` option
or the `CUSTOMIZER_ANSWERS` environment variable.

If you are a scaffold project maintainer and want to use this class to
provide a customizer for your project, you can copy this class to your
project, adjust the namespace, and implement the `questions()` method or
place the questions in an external file named `questions.php` anywhere in
your project to tailor the customizer to your scaffold's needs.

## Maintenance

    composer install
    composer lint
    composer test
    composer docs

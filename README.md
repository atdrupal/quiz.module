Quiz.module [![Build Status](https://travis-ci.org/atdrupal/quiz.module.svg?branch=7.x-6.x)](https://travis-ci.org/atdrupal/quiz.module) [![Gitter chat](https://badges.gitter.im/atdrupal/quiz.module.png)](https://gitter.im/atdrupal/quiz.module)
====
[![Gitter](https://badges.gitter.im/Join Chat.svg)](https://gitter.im/atdrupal/quiz.module?utm_source=badge&utm_medium=badge&utm_campaign=pr-badge&utm_content=badge)

Overview
--------

The quiz.module is a framework which allows you to create interactive quizzes 
for your visitors. It allows for the creation of questions of varying types, and
to collect those questions into quizzes. 

Features
--------

This list isn't complete (not even close)

 - Administrative features:
    o Assign feedback to responses to help point out places for further study
    o Supports multiple answers to quiz questions (on supporting question types)
    o Limit the number of takes users are allowed
    o Extensibility allows for additional question types to be added
    o Permissions (create/edit)
    o Randomize questions during the quiz
    o Assign only specific questions from the question bank

 - User features:
   o Can create/edit own quizzes.
   o Can take a quiz if have 'view quizzes' permissions, and receive score

Installation
------------

*Before* starting, make sure that you have read at least the introduction - so
you know at least the basic concepts. You can find it here:

                      http://drupal.org/node/298480

 * Quizz depends on ctools, entity, views, views_bulk_operations, xautoload
  modules, download and install them from
    http://drupal.org/project/ctools
    http://drupal.org/project/entity
    http://drupal.org/project/views
    http://drupal.org/project/views_bulk_operations
    http://drupal.org/project/xautoload
 * Copy the whole quizz directory to your modules directory
   (e.g. DRUPAL_ROOT/sites/all/modules) and activate the quiz and quiz_question
   modules and at least one question type module (for example, Multichoice).
 * The administrative user interface can be found at admin/config/workflow/rules

Configuration
-------------

1. Create a user role with the appropriate permissions.
   The role can be created under Administer >> User management >> Roles and the
  rights can be assigned to the role under Administer >> User management
  >> Access control. Assigning users to this role allows users other than the
  administrator to create questions and quizzes.

2. Add the "access quiz" permission to roles under Administer >> User
  management >> Access control that should be allowed to take the quizzes.

How to create a quiz
--------------------

1. Begin by creating a series of questions that you would like to include in
   the quiz. Go to Create content >> <question type> (for example, Multichoice).

2. Next, create a basic quiz by going to Create content >> Quiz. You will have
   the opportunity to set numerous options such as the number of questions,
   whether or not to shuffle question order, etc. When finished, click "Submit."

3. Finally, add questions to the quiz by clicking the "Manage questions" tab.
  Here you can also edit the order of the questions, and the max score for each
  question.

Credits
-------

- Original Quiz module's contributors.

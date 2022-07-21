<?php

use Doctrine\ORM\Tools\Console\ConsoleRunner;
use Doctrine\ORM\Tools\Console\EntityManagerProvider\SingleManagerProvider;

require 'db.php';

ConsoleRunner::run(new SingleManagerProvider($entityManager));

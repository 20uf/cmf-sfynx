<?php

/*
 * This file is part of the Sonata package.
 *
 * (c) Thomas Rabaix <thomas.rabaix@sonata-project.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sfynx\ClassificationBundle\Command;

use Sonata\ClassificationBundle\Model\ContextInterface;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\ClassLoader\ClassCollectionLoader;

class FixturesCommand extends ContainerAwareCommand
{

    /**
     * {@inheritDoc}
     */
    public function configure()
    {
        $this->setName('sfynx:classification:fixtures');
        $this->setDescription('Generate the default context if done defined and attach the context to all elements');
    }

    /**
     * {@inheritDoc}
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $contextManager = $this->getContainer()->get('sonata.classification.manager.context');
        $tagManager = $this->getContainer()->get('sonata.classification.manager.tag');
        $collectionManager = $this->getContainer()->get('sonata.classification.manager.collection');
        $categoryManager = $this->getContainer()->get('sonata.classification.manager.category');

        $output->writeln("1. Checking default context");

        $defaultContext = $contextManager->findOneBy(array(
            'id' => ContextInterface::DEFAULT_CONTEXT
        ));

        if (!$defaultContext) {
            $output->writeln(" > default context is missing, creating one");
            $defaultContext = $contextManager->create();
            $defaultContext->setId(ContextInterface::DEFAULT_CONTEXT);
            $defaultContext->setName('Default');
            $defaultContext->setEnabled(true);

            $contextManager->save($defaultContext);
        } else {
            $output->writeln(" > default context exists");
        }

        $output->writeln("2. Find tag without default context");

        foreach($tagManager->findBy(array()) as $tag) {
            if ($tag->getContext()) {
                continue;
            }

            $output->writeln(sprintf(" > attach default context to tag: %s (%s)", $tag->getSlug(), $tag->getId()));
            $tag->setContext($defaultContext);

            $tagManager->save($tag);
        }

        $output->writeln("3. Find collection without default context");

        foreach($collectionManager->findBy(array()) as $collection) {
            if ($collection->getContext()) {
                continue;
            }

            $output->writeln(sprintf(" > attach default context to collection: %s (%s)", $tag->getSlug(), $tag->getId()));
            $collection->setContext($defaultContext);

            $collectionManager->save($collection);
        }

        $output->writeln("3. Find category without default context");
        
        
        $allCategories = $categoryManager->findBy(array());
               
        if (!$allCategories) {
            $categoryManager->getRootCategory('default');
        } else {
            foreach($categoryManager->findBy(array()) as $category) {
                if ($category->getContext()) {
                    continue;
                }

                $output->writeln(sprintf(" > attach default context to collection: %s (%s)", $category->getSlug(), $category->getId()));
                $category->setContext($defaultContext);

                $categoryManager->save($category);
            }
        }

        $output->writeln("Done!");
    }
}

<?php

declare(strict_types=1);

namespace Doctrine\ODM\MongoDB\Tests\Functional;

use DateTime;
use Doctrine\ODM\MongoDB\Tests\BaseTest;
use Documents\BlogPost;
use Documents\BrowseNode;
use Documents\Cart;
use Documents\Comment;
use Documents\Customer;
use Documents\Feature;
use Documents\FriendUser;
use Documents\Product;
use Documents\Tag;
use function get_class;
use function strtotime;

class OwningAndInverseReferencesTest extends BaseTest
{
    public function testOneToOne()
    {
        // cart stores reference to customer
        // customer does not store reference to cart
        // customer has to load cart by querying for
        // db.cart.findOne({ 'customer.$id' : customer.id })

        // if inversedBy then isOwningSide
        // if mappedBy then isInverseSide

        $customer = new Customer();
        $customer->name = 'Jon Wage';
        $customer->cart = new Cart();
        $customer->cart->numItems = 5;
        $customer->cart->customer = $customer;
        $customer->cartTest = 'test';
        $this->dm->persist($customer);
        $this->dm->persist($customer->cart);
        $this->dm->flush();
        $this->dm->clear();

        $customer = $this->dm->getRepository('Documents\Customer')->find($customer->id);
        $this->assertInstanceOf('Documents\Cart', $customer->cart);
        $this->assertEquals($customer->cart->id, $customer->cart->id);

        $check = $this->dm->getDocumentCollection(get_class($customer))->findOne();
        $this->assertArrayHasKey('cart', $check);
        $this->assertEquals('test', $check['cart']);

        $customer->cart = null;
        $customer->cartTest = 'ok';
        $this->dm->flush();
        $this->dm->clear();

        $check = $this->dm->getDocumentCollection(get_class($customer))->findOne();
        $this->assertArrayHasKey('cart', $check);
        $this->assertEquals('ok', $check['cart']);

        $customer = $this->dm->getRepository('Documents\Customer')->find($customer->id);
        $this->assertInstanceOf('Documents\Cart', $customer->cart);
        $this->assertEquals('ok', $customer->cartTest);
    }

    public function testOneToManyBiDirectional()
    {
        $product = new Product('Book');
        $product->addFeature(new Feature('Pages'));
        $product->addFeature(new Feature('Cover'));
        $this->dm->persist($product);
        $this->dm->flush();
        $this->dm->clear();

        $check = $this->dm->getDocumentCollection(get_class($product))->findOne();
        $this->assertArrayNotHasKey('tags', $check);

        $check = $this->dm->getDocumentCollection('Documents\Feature')->findOne();
        $this->assertArrayHasKey('product', $check);

        $product = $this->dm->createQueryBuilder(get_class($product))
            ->getQuery()
            ->getSingleResult();
        $features = $product->features;
        $this->assertCount(2, $features);
        $this->assertEquals('Pages', $features[0]->name);
        $this->assertEquals('Cover', $features[1]->name);
    }

    public function testOneToManySelfReferencing()
    {
        $node = new BrowseNode('Root');
        $node->addChild(new BrowseNode('Child 1'));
        $node->addChild(new BrowseNode('Child 2'));

        $this->dm->persist($node);
        $this->dm->flush();
        $this->dm->clear();

        $check = $this->dm->getDocumentCollection(get_class($node))->findOne(['parent' => ['$exists' => false]]);
        $this->assertNotNull($check);
        $this->assertArrayNotHasKey('children', $check);

        $root = $this->dm->createQueryBuilder(get_class($node))
            ->field('children')->exists(false)
            ->getQuery()
            ->getSingleResult();
        $this->assertInstanceOf('Documents\BrowseNode', $root);
        $this->assertCount(2, $root->children);

        unset($root->children[0]);
        $this->dm->flush();

        $this->assertCount(1, $root->children);

        $this->dm->refresh($root);
        $this->assertCount(2, $root->children);
    }

    public function testManyToMany()
    {
        $baseballTag = new Tag('baseball');
        $blogPost = new BlogPost();
        $blogPost->name = 'Test';
        $blogPost->addTag($baseballTag);

        $this->dm->persist($blogPost);
        $this->dm->flush();
        $this->dm->clear();

        $check = $this->dm->getDocumentCollection(get_class($blogPost))->findOne();
        $this->assertCount(1, $check['tags']);

        $check = $this->dm->getDocumentCollection('Documents\Tag')->findOne();
        $this->assertArrayNotHasKey('blogPosts', $check);

        $blogPost = $this->dm->createQueryBuilder('Documents\BlogPost')
            ->getQuery()
            ->getSingleResult();
        $this->assertCount(1, $blogPost->tags);

        $this->dm->clear();

        $tag = $this->dm->createQueryBuilder('Documents\Tag')
            ->getQuery()
            ->getSingleResult();
        $this->assertEquals('baseball', $tag->name);
        $this->assertEquals(1, $tag->blogPosts->count());
        $this->assertEquals('Test', $tag->blogPosts[0]->name);
    }

    public function testManyToManySelfReferencing()
    {
        $jwage = new FriendUser('jwage');
        $fabpot = new FriendUser('fabpot');
        $fabpot->addFriend($jwage);
        $romanb = new FriendUser('romanb');
        $romanb->addFriend($jwage);
        $jwage->addFriend($fabpot);
        $jwage->addFriend($romanb);

        $this->dm->persist($jwage);
        $this->dm->persist($fabpot);
        $this->dm->persist($romanb);
        $this->dm->flush();
        $this->dm->clear();

        $check = $this->dm->createQueryBuilder('Documents\FriendUser')
            ->field('name')->equals('fabpot')
            ->hydrate(false)
            ->getQuery()
            ->getSingleResult();
        $this->assertArrayNotHasKey('friendsWithMe', $check);

        $user = $this->dm->createQueryBuilder('Documents\FriendUser')
            ->field('name')->equals('fabpot')
            ->getQuery()
            ->getSingleResult();

        $this->assertCount(1, $user->friendsWithMe);
        $this->assertEquals('jwage', $user->friendsWithMe[0]->name);

        $this->dm->clear();

        $user = $this->dm->createQueryBuilder('Documents\FriendUser')
            ->field('name')->equals('romanb')
            ->getQuery()
            ->getSingleResult();

        $this->assertCount(1, $user->friendsWithMe);
        $this->assertEquals('jwage', $user->friendsWithMe[0]->name);

        $this->dm->clear();

        $user = $this->dm->createQueryBuilder('Documents\FriendUser')
            ->field('name')->equals('jwage')
            ->getQuery()
            ->getSingleResult();

        $this->assertCount(2, $user->myFriends);
        $this->assertEquals('fabpot', $user->myFriends[0]->name);
        $this->assertEquals('romanb', $user->myFriends[1]->name);

        $this->assertCount(2, $user->friendsWithMe);
        $this->assertEquals('fabpot', $user->friendsWithMe[0]->name);
        $this->assertEquals('romanb', $user->friendsWithMe[1]->name);

        $this->dm->clear();
    }

    public function testSortLimitAndSkipReferences()
    {
        $date1 = new DateTime();
        $date1->setTimestamp(strtotime('-20 seconds'));

        $date2 = new DateTime();
        $date2->setTimestamp(strtotime('-10 seconds'));

        $blogPost = new BlogPost('Test');
        $blogPost->addComment(new Comment('Comment 1', $date1));
        $blogPost->addComment(new Comment('Comment 2', $date2));

        $this->dm->persist($blogPost);
        $this->dm->flush();
        $this->dm->clear();

        $blogPost = $this->dm->createQueryBuilder('Documents\BlogPost')
            ->getQuery()
            ->getSingleResult();
        $this->assertEquals('Comment 1', $blogPost->comments[0]->text);
        $this->assertEquals('Comment 2', $blogPost->comments[1]->text);
        $this->assertEquals('Test', $blogPost->comments[0]->parent->name);
        $this->assertEquals('Test', $blogPost->comments[1]->parent->name);

        $this->dm->clear();

        $comment = $this->dm->createQueryBuilder('Documents\Comment')
            ->getQuery()
            ->getSingleResult();
        $this->assertEquals('Test', $comment->parent->getName());

        $this->dm->clear();

        $blogPost = $this->dm->createQueryBuilder('Documents\BlogPost')
            ->getQuery()
            ->getSingleResult();
        $this->assertEquals('Comment 1', $blogPost->firstComment->getText());
        $this->assertEquals('Comment 2', $blogPost->latestComment->getText());
        $this->assertCount(2, $blogPost->last5Comments);

        $this->assertEquals('Comment 2', $blogPost->last5Comments[0]->getText());
        $this->assertEquals('Comment 1', $blogPost->last5Comments[1]->getText());

        $this->dm->clear();

        $blogPost = $this->dm->createQueryBuilder('Documents\BlogPost')
            ->getQuery()
            ->getSingleResult();

        $blogPost->addComment(new Comment('Comment 3 by admin', $date1, true));
        $blogPost->addComment(new Comment('Comment 4 by admin', $date2, true));
        $this->dm->flush();
        $this->dm->clear();

        $blogPost = $this->dm->createQueryBuilder('Documents\BlogPost')
            ->getQuery()
            ->getSingleResult();
        $this->assertCount(2, $blogPost->adminComments);
        $this->assertEquals('Comment 4 by admin', $blogPost->adminComments[0]->getText());
        $this->assertEquals('Comment 3 by admin', $blogPost->adminComments[1]->getText());
    }
}

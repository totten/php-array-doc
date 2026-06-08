<?php
namespace PhpArrayDocument\Tests;

use PHPUnit\Framework\TestCase;
use PhpArrayDocument\Parser;
use PhpArrayDocument\ScalarNode;
use PhpArrayDocument\ArrayNode;
use PhpArrayDocument\ArrayItemNode;

class FindPathTest extends TestCase {

  private function getDocumentRoot(string $file): ArrayNode {
    $parser = new Parser();
    $phpCode = file_get_contents($file);
    $document = $parser->parse($phpCode);
    return $document->getRoot();
  }

  public function testFindPathTopLevel(): void {
    $root = $this->getDocumentRoot('examples/SavedSearch_Zero_balance_holdings.mgd.php');
    $nodes = iterator_to_array($root->findPath(['*']));
    $this->assertCount(3, $nodes);
    $this->assertContainsOnlyInstancesOf(ArrayNode::class, $nodes);
  }

  public function testFindPathSpecificKey(): void {
    $root = $this->getDocumentRoot('examples/SavedSearch_Zero_balance_holdings.mgd.php');
    $nodes = iterator_to_array($root->findPath(['*', 'name']));
    $this->assertCount(3, $nodes);
    // Two top-level names, one inner 'name' for 'api_entity'
    $this->assertContainsOnlyInstancesOf(ScalarNode::class, $nodes);
    $names = array_map(fn($node) => $node->getScalar(), $nodes);
    $this->assertContains('SavedSearch_Zero_balance_holdings', $names);
    $this->assertContains('SavedSearch_Zero_balance_holdings_SearchDisplay_Zero_balance_holdings_tab', $names);
    $this->assertContains('SavedSearch_Zero_balance_holdings_SearchDisplay_Zero_balance_register', $names);
  }

  public function testFindPathNestedScalar(): void {
    $root = $this->getDocumentRoot('examples/SavedSearch_Zero_balance_holdings.mgd.php');
    $nodes = iterator_to_array($root->findPath(['*', 'params', 'values', 'settings', 'description']));
    $this->assertCount(2, $nodes);
    $this->assertContainsOnlyInstancesOf(ScalarNode::class, $nodes);
    foreach ($nodes as $node) {
      $this->assertEquals('', $node->getScalar());
    }
  }

  public function testFindPathNestedWildcardArrayItem(): void {
    $root = $this->getDocumentRoot('examples/SavedSearch_Zero_balance_holdings.mgd.php');
    $nodes = iterator_to_array($root->findPath(['*', 'params', 'values', 'settings', 'sort', '*', '*']));
    // Expected nodes:
    // First top-level (SavedSearch): no 'sort'
    // Second top-level (SearchDisplay tab):
    //   sort[0][0]: 'ShareHolding_ShareIssue_issue_id_01.society_id.display_name'
    //   sort[0][1]: 'ASC'
    //   sort[1][0]: 'issue_id.name'
    //   sort[1][1]: 'ASC'
    // Third top-level (SearchDisplay register):
    //   sort[0][0]: 'contact_id.sort_name'
    //   sort[0][1]: 'ASC'
    $this->assertCount(6, $nodes);
    $this->assertContainsOnlyInstancesOf(ScalarNode::class, $nodes);
    $values = array_map(fn($node) => $node->getScalar(), $nodes);
    $this->assertContains('ShareHolding_ShareIssue_issue_id_01.society_id.display_name', $values);
    $this->assertContains('issue_id.name', $values);
    $this->assertContains('contact_id.sort_name', $values);
    $this->assertContains('ASC', $values);
  }

  public function testFindPathNestedWildcardSpecificKey(): void {
    $root = $this->getDocumentRoot('examples/SavedSearch_Zero_balance_holdings.mgd.php');
    $nodes = iterator_to_array($root->findPath(['*', 'params', 'values', 'settings', 'columns', '*', 'label']));
    // Expected: The 'label' from each column definition in the two SearchDisplay arrays.
    // Count them from the file:
    // Tab: 7 columns with labels
    // Register: 6 columns with labels
    $this->assertCount(11, $nodes);
    $this->assertContainsOnlyInstancesOf(ScalarNode::class, $nodes);
    $labels = array_map(fn($node) => $node->getScalar(), $nodes);
    $this->assertContains('ID', $labels);
    $this->assertContains('Society', $labels);
    $this->assertContains('Issue', $labels);
    $this->assertContains('Interest Choice', $labels);
    $this->assertContains('Capital', $labels);
    $this->assertContains('Payable', $labels);
    $this->assertContains('Holder', $labels);
  }

  public function testFindPathNonExistent(): void {
    $root = $this->getDocumentRoot('examples/SavedSearch_Zero_balance_holdings.mgd.php');
    $nodes = iterator_to_array($root->findPath(['*', 'nonExistentKey']));
    $this->assertEmpty($nodes);
  }

  public function testFindPathOnScalarNode(): void {
    $root = $this->getDocumentRoot('examples/SavedSearch_Zero_balance_holdings.mgd.php');
    // Find a scalar node first, then try to search from it.
    $nameNode = iterator_to_array($root->findPath(['*', 'name']))[0];
    $this->assertInstanceOf(ScalarNode::class, $nameNode);

    $nodes = iterator_to_array($nameNode->findPath(['someKey']));
    $this->assertEmpty($nodes);
    // ScalarNode cannot have children, so findPath should yield nothing.
  }

  public function testFindPathFromArrayItemNode(): void {
    $parser = new Parser();
    $document = $parser->parse('<?php return ["foo" => ["bar" => "baz"]];');
    $root = $document->getRoot();

    // Get the ArrayItemNode for "foo"
    $fooItem = $root->getItem('foo');
    $this->assertInstanceOf(ArrayItemNode::class, $fooItem);

    // Search from the ArrayItemNode's value (which is an ArrayNode)
    $nodes = iterator_to_array($fooItem->getValue()->findPath(['bar']));
    $this->assertCount(1, $nodes);
    $this->assertInstanceOf(ScalarNode::class, $nodes[0]);
    $this->assertEquals('baz', $nodes[0]->getScalar());

    // Searching directly on ArrayItemNode itself should yield nothing for non-empty path
    $nodes = iterator_to_array($fooItem->findPath(['bar']));
    $this->assertEmpty($nodes);
  }

}

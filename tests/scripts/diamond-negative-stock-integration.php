<?php
declare(strict_types=1);
$root=dirname(__DIR__, 2); define('FCPATH',$root.'/public/'); define('ENVIRONMENT','development');
require $root.'/app/Config/Paths.php'; $paths=new Config\Paths(); require $paths->systemDirectory.'/Boot.php'; CodeIgniter\Boot::bootConsole($paths);
$db=Config\Database::connect(); $expected=getenv('DIAMOND_STOCK_TEST_DB'); if (! is_string($expected) || ! preg_match('/^codex_[a-z0-9_]+$/', $expected) || $db->database !== $expected || ! in_array($db->hostname, ['127.0.0.1', 'localhost'], true)) throw new RuntimeException('Set DIAMOND_STOCK_TEST_DB to the configured disposable codex_ database on localhost.');
$service=new App\Services\DiamondInventory\StockService($db);
function eq($label,$actual,$expected): void {if(abs((float)$actual-(float)$expected)>0.001) throw new RuntimeException("$label: actual $actual expected $expected");}
function fails($label,callable $f):void {try {$f();} catch (RuntimeException $e) {return;} throw new RuntimeException("$label did not fail");}
function val($db,$id):array{return $db->table('stock')->where('item_id',$id)->get()->getRowArray();}
function row($db,$table,$data):int {$db->table($table)->insert($data); return (int)$db->insertID();}
$db->transStart();
try {
 $item=row($db,'items',['diamond_type'=>'TEST-NEGATIVE-DIAMOND']);
 $issue=row($db,'issue_headers',['issue_date'=>'2026-09-08','voucher_no'=>'TEST-NEGATIVE']);
 row($db,'issue_lines',['issue_id'=>$issue,'item_id'=>$item,'pcs'=>0,'carat'=>4.720]);
 fails('normal issue',fn()=>$service->applyIssue($issue));
 $service->applyIssue($issue,true); $s=val($db,$item);eq('initial deficit',$s['carat_balance'],-4.720);eq('negative stock value',$s['stock_value'],0);
 $controller=new App\Controllers\Admin\DiamondInventory\ItemsController();$controller->delete($item);
 if(!$db->table('items')->where('id',$item)->countAllResults()) throw new RuntimeException('Deletion guard allowed negative stock deletion');
 $p1=row($db,'purchase_headers',['purchase_date'=>'2026-09-09']);row($db,'purchase_lines',['purchase_id'=>$p1,'item_id'=>$item,'pcs'=>0,'carat'=>2,'rate_per_carat'=>100,'line_value'=>200]);
 $service->applyPurchase($p1); $s=val($db,$item);eq('partial purchase',$s['carat_balance'],-2.720);eq('partial purchase valuation',$s['stock_value'],0);
 $service->reversePurchase($p1);eq('reverse partial purchase',val($db,$item)['carat_balance'],-4.720);$service->applyPurchase($p1);
 $ret=row($db,'return_headers',['return_date'=>'2026-09-10','issue_id'=>$issue]);row($db,'return_lines',['return_id'=>$ret,'item_id'=>$item,'pcs'=>0,'carat'=>1]);
 $service->applyReturn($ret);eq('partial return',val($db,$item)['carat_balance'],-1.720);$service->reverseReturn($ret);eq('reverse return',val($db,$item)['carat_balance'],-2.720);
 $adj=row($db,'diamond_inventory_adjustment_headers',['adjustment_date'=>'2026-09-10','adjustment_type'=>'add']);row($db,'diamond_inventory_adjustment_lines',['adjustment_id'=>$adj,'item_id'=>$item,'pcs'=>0,'carat'=>1,'rate_per_carat'=>80,'line_value'=>80]);
 $service->applyAdjustment($adj,'add');eq('partial add adjustment',val($db,$item)['carat_balance'],-1.720);$service->reverseAdjustment($adj,'add');eq('reverse adjustment',val($db,$item)['carat_balance'],-2.720);
 fails('unvalued issue reversal crossing',fn()=>$service->reverseIssue($issue));
 $p2=row($db,'purchase_headers',['purchase_date'=>'2026-09-11']);row($db,'purchase_lines',['purchase_id'=>$p2,'item_id'=>$item,'pcs'=>0,'carat'=>3,'rate_per_carat'=>120,'line_value'=>360]);
 $service->applyPurchase($p2);$s=val($db,$item);eq('purchase crosses zero',$s['carat_balance'],0.280);eq('surplus purchase rate',$s['avg_cost_per_carat'],120);eq('surplus stock value',$s['stock_value'],33.60);
 fails('reverse crossing purchase',fn()=>$service->reversePurchase($p2));eq('blocked purchase reversal preserves stock',val($db,$item)['carat_balance'],0.280);
 $out=row($db,'issue_headers',['issue_date'=>'2026-09-12']);row($db,'issue_lines',['issue_id'=>$out,'item_id'=>$item,'pcs'=>0,'carat'=>1]);fails('normal issue still blocks new deficit',fn()=>$service->applyIssue($out));eq('blocked issue leaves stock',val($db,$item)['carat_balance'],0.280);
 $item3=row($db,'items',['diamond_type'=>'TEST-ADJUSTED-DIAMOND']);
 $issue3=row($db,'issue_headers',['issue_date'=>'2026-09-08']);row($db,'issue_lines',['issue_id'=>$issue3,'item_id'=>$item3,'pcs'=>0,'carat'=>4.720]);$service->applyIssue($issue3,true);
 $adj3=row($db,'diamond_inventory_adjustment_headers',['adjustment_date'=>'2026-09-11','adjustment_type'=>'add']);row($db,'diamond_inventory_adjustment_lines',['adjustment_id'=>$adj3,'item_id'=>$item3,'pcs'=>0,'carat'=>5,'rate_per_carat'=>80,'line_value'=>400]);
 $service->applyAdjustment($adj3,'add');$s=val($db,$item3);eq('adjustment crosses zero',$s['carat_balance'],0.280);eq('surplus adjustment rate',$s['avg_cost_per_carat'],80);eq('surplus adjustment value',$s['stock_value'],22.40);fails('reverse crossing adjustment',fn()=>$service->reverseAdjustment($adj3,'add'));eq('blocked adjustment reversal preserves stock',val($db,$item3)['carat_balance'],0.280);
 $item2=row($db,'items',['diamond_type'=>'TEST-NORMAL-DIAMOND']);row($db,'stock',['item_id'=>$item2,'pcs_balance'=>0,'carat_balance'=>10,'avg_cost_per_carat'=>50,'stock_value'=>500]);
 $p3=row($db,'purchase_headers',['purchase_date'=>'2026-09-11']);row($db,'purchase_lines',['purchase_id'=>$p3,'item_id'=>$item2,'pcs'=>0,'carat'=>2,'rate_per_carat'=>100,'line_value'=>200]);$service->applyPurchase($p3);$s=val($db,$item2);eq('normal purchase qty',$s['carat_balance'],12);eq('normal purchase avg',$s['avg_cost_per_carat'],58.33);eq('normal purchase value',$s['stock_value'],700);
 echo "PASS: negative opt-in, partial purchase, return, adjustment, reversals, safe crossing, normal stock, and deletion guard.\n";
} finally {while($db->transRollback()) {}}

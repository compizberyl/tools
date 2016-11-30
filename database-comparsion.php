<?php

if(!isset($_REQUEST['host1']))$_REQUEST['host1'] = 'XX.XX.XX.XX';

if(!isset($_REQUEST['user1']))$_REQUEST['user1'] = 'xx';
if(!isset($_REQUEST['pwd1']))$_REQUEST['pwd1'] = 'xxx';
if(!isset($_REQUEST['db1']))$_REQUEST['db1'] = 'xxx';

if(!isset($_REQUEST['host2']))$_REQUEST['host2'] = 'XX.XX.XX.XX';
if(!isset($_REQUEST['user2']))$_REQUEST['user2'] = 'xx';
if(!isset($_REQUEST['pwd2']))$_REQUEST['pwd2'] = 'xx';
if(!isset($_REQUEST['db2']))$_REQUEST['db2'] = 'xx';


isset($_REQUEST['exec']) || $_REQUEST['exec'] = 0;

header('Content-Type: text/html;charset=utf-8');

?><html>
<title>数据库结构异同对比器</title>
<style>
*{
font: 12px 'verdana';
}
</style>
<body>
<form method="POST">
<h3>源数据库信息：</h3>
主机：<input type="text" name="host1" value="<?php echo $_REQUEST['host1']?>"><br>
用户名：<input type="text" name="user1" value="<?php echo $_REQUEST['user1']?>"><br>
密码：<input type="password" name="pwd1" value="<?php echo $_REQUEST['pwd1']?>"><br>
库名：<input type="text" name="db1" value="<?php echo $_REQUEST['db1']?>"><br>
<h3>目标数据库信息：</h3>
主机：<input type="text" name="host2" value="<?php echo $_REQUEST['host2']?>"><br>
用户名：<input type="text" name="user2" value="<?php echo $_REQUEST['user2']?>"><br>
密码：<input type="password" name="pwd2" value="<?php echo $_REQUEST['pwd2']?>"><br>
库名：<input type="text" name="db2" value="<?php echo $_REQUEST['db2']?>"><br>
<input type="submit" value="提交">
<input type="reset" value="重置">
<p><input type="checkbox" name="exec" value="1" <?php echo ( $_REQUEST['exec'] == 1 ) ? 'checked' : '' ?> >执行SQL更新操作</p>
</form>


<?php
/**
 * 数据库结构检查
 *
 */

function getDB( $dbNum )
{
	static $link = null;

	if ( $link ) {
		mysqli_close( $link );
		$link = null;
	}

	$link = mysqli_connect(  $_REQUEST['host' . $dbNum ] , $_REQUEST['user' . $dbNum ] , $_REQUEST['pwd' . $dbNum ] )
		or die( '数据库' . $dbNum . ' 连接失败！');

	mysqli_select_db($link, $_REQUEST['db' . $dbNum ])
		or die('数据库' . $dbNum . ' 选择失败' . mysqli_error() );

	mysqli_query( $link,'set names "utf8";')
		or die('数据库' . $dbNum . ' 设置编码失败');

	return array('dbNum'=>$dbNum , 'link' => $link );
}

function Query( $sql , $link = null )
{
	if ( ! is_resource( $link['link'] ) || ! @mysqli_ping( $link['link'] ) ) {
		$link = getDB( $link['dbNum'] );
	}
	$ret = mysqli_query( $link['link'],$sql);
	if ( ! $ret ) {
		if ( mysqli_errno($link['link'] ) == 2013 ) { //Lost Connect错误出现时重新进行数据库连接
			$link = getDB( $link['dbNum'] );
			return Query( $sql , $link );
		}
	}

	return $ret;
}


if ( ! isset( $_REQUEST['host1'] ) || ! isset( $_REQUEST['host2'] ) ) {
	echo '</form></body></html>';
    exit;
}

echo '<hr>';

set_time_limit( 0 );
//ignore_user_abort( true );
ob_implicit_flush( true );


//mysqli_select_db( $_REQUEST['db1'] , $link1 )
//			or die( '数据库1 选择失败！');
//mysqli_select_db( $_REQUEST['db2'] , $link2 )
//			or die( '数据库2 选择失败！');
$ts = array();
$tStruct = array();
$tCreate = array();
$tIndex = array();
foreach( array( $_REQUEST['db1'] => 1 , $_REQUEST['db2'] => 2 ) as $db => $linkNo ) {
	$link = getDB( $linkNo );

    //查询表结构
    $m = Query( 'show tables' , $link )
    	or die ( $db . ':show tables 执行失败！');


    while( $r = mysqli_fetch_array( $m ) ) {
        $tn =  $r[0];

        $rm = Query( 'show full columns from `' . $tn . '`' , $link )
        		or die( '失败！' . $db . ':show full columns from `' . $tn . '`' );
        while( $v = mysqli_fetch_assoc( $rm ) ) {
            $ts[$db][$tn][] = $v['Field'];
            $tStruct[$db][$tn][$v['Field']] = $v;
        }
        if ( $db == $_REQUEST['db1'] ) {
	        $rm = Query( 'show create table `' . $tn . '`' , $link )
	        	or die( '失败！' . $db . ':show create table `' . $tn . '`' );
	        $v = mysqli_fetch_array( $rm );
	        $tCreate[$v[0]] = $v[1] ;
        }

        //查询表结构
        $rm = Query( 'SHOW INDEX FROM `' . $tn . '`' , $link )
        		or die( '失败！' . $db . '<br />ERROR:' . mysqli_error() . '<br />ENO:' . mysqli_errno() . '<br />SHOW INDEX FROM `' . $tn . '`' );;
        $t = array();
        while( $v = mysqli_fetch_assoc( $rm ) ) {
        	if ( $v['Key_name'] == 'PRIMARY' ) {
        		//主键
        		$t['key'][$v['Key_name']][] = $v['Column_name'];
        		continue;
        	}
        	if ( $v['Non_unique'] == 1 ) {
        		//索引
        		if ( $v['Index_type'] == 'FULLTEXT' ) {
        			//全文索引
        			$t['fulltext'][$v['Key_name']][] = $v['Column_name'];
        		} else {
        			//一般索引
        			$t['index'][$v['Key_name']][] = $v['Column_name'];
        		}
        	} else {
        		//唯一
        		$t['uni'][$v['Key_name']][] = $v['Column_name'];
        	}
        }
//        if ( $tn == 'access_log') {
//        	var_dump( $t );
//        }
        foreach( $t as $type => $ta ) {
	        foreach( $ta as $kn => $ka ) {
	        	sort( $ka );
//	        	var_dump( $ka );
	        	$tmp = implode( '`,`' , $ka );
	        	if ( ! empty( $tmp ) ) {
	        		$tmp = '`' . $tmp . '`' ;
	        	}
	        	$ta[$kn] = $tmp;
	        }
	        $t[$type] = $ta;
        }
        $tIndex[$db][$tn] = $t;
    }
}


$sql = array();
$msg = array();
$skipCheckIndex = array();

if ( is_array( $ts[$_REQUEST['db1']] ) ) foreach( $ts[$_REQUEST['db1']] as $tn => $tbs ) {
    if ( ! isset( $ts[$_REQUEST['db2']][$tn] ) ) {
        //创建新表
        $sql[] = $tCreate[$tn];
    }
    $last = '首';
    foreach( $tbs as $v ) {
        if ( ! isset( $ts[$_REQUEST['db2']][$tn] ) ) {
             $msg[$tn][] = '新添（表添加） ' . $v . ' 于 ' . $last . ' 之后';
             $last = $v;
             $skipCheckIndex[$tn] = 1;
             continue;
        }

        $fieldUpdateMethod = '' ;
        if ( ! in_array( $v , $ts[$_REQUEST['db2']][$tn] ) ) {
            //新添字段
            $msg[$tn][] = '新添 ' . $v . ' 于 ' . $last . ' 之后';
            $fieldUpdateMethod = 'ADD';
        } else {
            //更新字段

            if ( $tStruct[$_REQUEST['db2']][$tn][$v] != $tStruct[$_REQUEST['db1']][$tn][$v] ) {

                $msg[$tn][] = '更新 ' . $v . ' 于 ' . $last . ' 之后';
                $fieldUpdateMethod = 'CHANGE `' . $v . '` ' ;
            }
        }

        if ( $fieldUpdateMethod ) {
            $T = $tStruct[$_REQUEST['db1']][$tn];
            $tt = $T[$v];

            $tSql = 'alter table `' . $tn .'` ' . $fieldUpdateMethod . ' `' . $v .'` ' . $tt['Type'] . ' ';
            if ( $tt['Null'] == 'NO' ) {
                $tSql .= ' NOT NULL ';
            }

            $tSql .= ' ' . $tt['Extra'] . ' ';

            if ( $tt['Default'] != '' ) {
            	if ( $tt['Default'] == 'CURRENT_TIMESTAMP' ) {
            		$tSql .= ' default CURRENT_TIMESTAMP ';
            	} else {
                	$tSql .= ' default \'' . addslashes( $tt['Default'] ) . '\' ';
            	}
            }

            //$tSql .= 'COMMENT \'' .  mysqli_real_escape_string($link['link'], $tt['Comment'] ) .'\' ';
            $tt['Comment'] = addslashes($tt['Comment']);
            $tSql .= "comment '{$tt['Comment']}'";
            if ( $last == '首' ) {
                $tSql .= ' FIRST ';
            } else {
                if ( in_array( $last , $ts[$_REQUEST['db2']][$tn] ) ) {
                    $tSql .= ' AFTER `' . $last . '`';
                }
            }

            //由最后进行统一的索引更新，所以此处将注释掉

            if ( ! empty( $tt['Extra'] ) ) {

	            if ( $tt['Key'] == 'PRI' ) {
	                //主键,需要先放弃之前的主键，再重新创建
	                $tSql .= ' , DROP PRIMARY KEY ';
	                //查询当前的所有主键的字段名
	                $tSql .= ' , ADD PRIMARY KEY ( ';
	                foreach( $T as $tName => $vv ) {
	                    $priKey = array();
	                    if ( $vv['Key'] == 'PRI' ) {
	                        $priKey[] = '`' . $tName . '`';
	                    }
	                    $tSql .= implode( ',' , $priKey );
	                }
	                $tSql .= ' ) ';
	            }


	            if ( $tt['Key'] == 'MUL' ) {
	                //索引
	                $tSql .= ' , ADD INDEX ( ' . $v . ' )';
	            }

	            if ( $tt['Key'] == 'UNI' ) {
	                //索一
	                $tSql .= ' , ADD UNIQUE ( ' . $v . ' )';
	            }
            }
            $sql[] = $tSql;
        }
        $last = $v;
    }

}

if ( is_array( $ts[$_REQUEST['db2']] ) ) foreach( $ts[$_REQUEST['db2']] as $tn => $tbs ) {
	if ( ! isset( $ts[$_REQUEST['db1']][$tn]) ) {
		//表不存在，drop表
		$msg[$tn][] = '删除(表删除) ' . $tn;
		$sql[] = 'drop table `' . $tn . '`';
		$skipCheckIndex[$tn] = 1;
		continue;
	}


    foreach( $tbs as $v ) {

        if ( ! in_array( $v , (array) $ts[$_REQUEST['db1']][$tn] ) ) {
            $msg[$tn][] = '删除 ' . $v;
            $sql[] = 'alter table `' . $tn . '` drop column `' . $v . '`';
        }
    }
}

$typeMap = array(
	'key' => '主键' ,
	'index' => '索引' ,
	'uni' => '唯一' ,
	'fulltext' => '全文索引' ,
);

//这里开始进行索引比对
echo '<b>数据库的索引对比</b>';
$indexMsg = array();
foreach( $tIndex[$_REQUEST['db2']] as $tableName => $indexs ) {
	$str = '';
	foreach( $indexs as $type => $arr ) {
		foreach( $arr as $iName => $iVal ) {
			if ( isset( $tIndex[$_REQUEST['db1']][$tableName][$type][$iName] ) ) {
				$srcVal = $tIndex[$_REQUEST['db1']][$tableName][$type][$iName];
				if ( $srcVal == $iVal || isset( $skipCheckIndex[$tableName]) ) { //值相等或是新添加、待删除的表，则不作索引检查
					//相等，跳过
					continue;
				} else {
					//不相等，重新生成
					$msg[$tableName][] = '<li>' . $typeMap[$type] .' [' . $iName .' : ' . $iVal . '] 异同';

					//删除原来的索引
					switch( $type ) {
						case 'key' :
							$sql[] = 'ALTER TABLE `'. $tableName . '` DROP PRIMARY KEY ';
							$sql[] = 'ALTER TABLE `' . $tableName . '` ADD PRIMARY KEY ( ' . $srcVal . ' )';
							break;
						case 'index' :
							$sql[] = 'ALTER TABLE `'. $tableName . '` DROP INDEX `' . $iName .'`';
							$sql[] = 'ALTER TABLE `' . $tableName . '` ADD INDEX `' . $iName .'` ( ' . $srcVal . ' )';
							break;
						case 'uni' :
							$sql[] = 'ALTER TABLE `'. $tableName . '` DROP INDEX `' . $iName .'`';
							$sql[] = 'ALTER TABLE `' . $tableName . '` ADD UNIQUE `' . $iName .'` ( ' . $srcVal . ' )';
							break;
						case 'fulltext' :
							$sql[] = 'ALTER TABLE `'. $tableName . '` DROP INDEX `' . $iName .'`';
							$sql[] = 'ALTER TABLE `' . $tableName . '` ADD FULLTEXT `' . $iName .'` ( ' . $srcVal . ' )';
							break;
					}

				}
			} else {
				//不存在此键，删除
				$msg[$tableName][] = '<li>'. $typeMap[$type] .' [' . $iName .' : ' . $iVal . '] 需要删除';
				switch( $type ) {
					case 'key' :
						$sql[] = 'ALTER TABLE `'. $tableName . '` DROP PRIMARY KEY ';
						break;
					case 'index' :
					case 'uni' :
					case 'fulltext' :
						$sql[] = 'ALTER TABLE `'. $tableName . '` DROP INDEX `' . $iName .'`';
						break;
				}
			}
		}
	}

}

foreach( $tIndex[$_REQUEST['db1']] as $tableName => $indexs ) {
	foreach( $indexs as $type => $arr ) {
		foreach( $arr as $iName => $iVal ) {
			if ( isset( $tIndex[$_REQUEST['db2']][$tableName][$type][$iName] ) ) {

			} else {
				//不存在此键，需要添加
				$msg[$tableName][] = '<li>'. $typeMap[$type] .' [' . $iName .' : ' . $iVal . '] 需要添加';

				if ( ! isset( $skipCheckIndex[$tableName]) ) {

					switch( $type ) {
						case 'key' :
							$sql[] = 'ALTER TABLE `' . $tableName . '` ADD PRIMARY KEY ( ' . $iVal . ' )';
							break;
						case 'index' :
							$sql[] = 'ALTER TABLE `' . $tableName . '` ADD INDEX `' . $iName .'` ( ' . $iVal . ' )';
							break;
						case 'uni' :
							$sql[] = 'ALTER TABLE `' . $tableName . '` ADD UNIQUE `' . $iName .'` ( ' . $iVal . ' )';
							break;
						case 'fulltext' :
							$sql[] = 'ALTER TABLE `' . $tableName . '` ADD FULLTEXT `' . $iName .'` ( ' . $iVal . ' )';
							break;
					}

				}
			}
		}
	}

}


//索引比对结束


foreach( $msg as $tn => $arr ) {
    echo '<hr><p>表名：' . $tn . '</p><ul>';
    if ( !empty( $arr ) ) foreach( $arr as $v ) {
        echo '<li>' . $v . '</li>';
    } else {
        echo '<li>没有更新</li>';
    }
    echo '</ul>';
}

if ( ! empty( $sql ) ) {

echo '<textarea style="width:100%;height:500px">';

$result = '';
foreach( $sql as $s )echo htmlspecialchars( $s . ";\n\n" );
echo '</textarea>';

if ( $_REQUEST['exec'] == 1 ) {
	$totalSqls = count( $sql );
	$link2 = getDB( 2 );
	foreach( $sql as $i => $s ) {
		echo '===========================完成度：' . ( $i + 1 ) . ' / ' . $totalSqls .'=================================<br />';
    	echo '<p><b>执行：</b>' . $s . ' ' .  ( Query( $s , $link2 ) ? '<font color=green>成功</font>' : '<font color=red>失败</font>' );
    	echo "</p>\r\n";
    }
}

?>
<table><tr><td>
<form method="POST">
<input type="hidden" name="exec" value="1">
<input type="hidden" name="host1" value="<?php echo $_REQUEST['host1']?>"><br>
<input type="hidden" name="user1" value="<?php echo $_REQUEST['user1']?>"><br>
<input type="hidden" name="pwd1" value="<?php echo $_REQUEST['pwd1']?>"><br>
<input type="hidden" name="db1" value="<?php echo $_REQUEST['db1']?>"><br>
<input type="hidden" name="host2" value="<?php echo $_REQUEST['host2']?>"><br>
<input type="hidden" name="user2" value="<?php echo $_REQUEST['user2']?>"><br>
<input type="hidden" name="pwd2" value="<?php echo $_REQUEST['pwd2']?>"><br>
<input type="hidden" name="db2" value="<?php echo $_REQUEST['db2']?>"><br>


<input type="submit" value="执行SQL以便更新表结构">
</form>
</td><td>
<form method="POST">
<input type="hidden" name="host1" value="<?php echo $_REQUEST['host1']?>"><br>
<input type="hidden" name="user1" value="<?php echo $_REQUEST['user1']?>"><br>
<input type="hidden" name="pwd1" value="<?php echo $_REQUEST['pwd1']?>"><br>
<input type="hidden" name="db1" value="<?php echo $_REQUEST['db1']?>"><br>
<input type="hidden" name="host2" value="<?php echo $_REQUEST['host2']?>"><br>
<input type="hidden" name="user2" value="<?php echo $_REQUEST['user2']?>"><br>
<input type="hidden" name="pwd2" value="<?php echo $_REQUEST['pwd2']?>"><br>
<input type="hidden" name="db2" value="<?php echo $_REQUEST['db2']?>"><br>
<input type="submit" value="再次查询（不执行SQL）">
</form>
</td></tr></table>
<?php
} else {
	echo '<font color=green>数据库之间没有差异！</font>';
}

?>
</body>
</html>

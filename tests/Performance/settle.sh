#!/bin/bash
# Waits until the scratch MySQL has finished the background work left by the previous run (InnoDB purge, dirty pages, redo checkpoint
# age), so consecutive measurements start from the same state. (Without it the same import alternates between ~50 s and ~115 s.)
php -r 'for($i=0;$i<120;$i++){$p=new PDO("mysql:host=127.0.0.1;port=3391;dbname=lspu_eis_perf","root","");$st=$p->query("show engine innodb status")->fetch(PDO::FETCH_NUM)[2];preg_match("/History list length (\d+)/",$st,$h);preg_match("/Log sequence number\s+(\d+)/",$st,$l);preg_match("/Last checkpoint at\s+(\d+)/",$st,$c);$d=(int)$p->query("show global status like \"Innodb_buffer_pool_pages_dirty\"")->fetch(PDO::FETCH_NUM)[1];if((int)$h[1]<300&&($l[1]-$c[1])<40*1048576&&$d<3000){echo "settled after ",$i*5,"s\n";exit;}sleep(5);}echo "settle timed out\n";'
sleep 5

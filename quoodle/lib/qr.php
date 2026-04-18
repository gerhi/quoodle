<?php
/**
 * Quoodle QR Code Generator
 * Pure PHP, no external dependencies.
 * Byte mode, error correction level M, versions 1–10, SVG output.
 */
class QrCode {

    // Max data bytes per version, level M (byte mode)
    private static array $CAP = [
        1=>14,2=>26,3=>42,4=>62,5=>84,6=>106,7=>122,8=>152,9=>180,10=>214,
    ];

    // [ec_cw_per_block, g1_blocks, g1_data_cw, g2_blocks, g2_data_cw]
    private static array $BLK = [
        1  =>[10,1,16,0, 0], 2  =>[16,1,28,0, 0], 3  =>[13,2,22,0, 0],
        4  =>[18,2,32,0, 0], 5  =>[24,2,43,0, 0], 6  =>[16,4,27,0, 0],
        7  =>[18,4,31,0, 0], 8  =>[22,2,38,2,39], 9  =>[22,3,36,2,37],
        10 =>[26,4,43,1,44],
    ];

    // Alignment-pattern centre positions per version
    private static array $ALN = [
        2=>[6,18],3=>[6,22],4=>[6,26],5=>[6,30],6=>[6,34],
        7=>[6,22,38],8=>[6,24,42],9=>[6,26,46],10=>[6,28,50],
    ];

    // Remainder bits appended after codeword stream (v1..v10)
    private static array $REM = [0,7,7,7,7,7,0,0,0,0];

    // GF(256)
    private static array $EXP=[], $LOG=[];
    private static bool  $GF=false;

    private static function gfInit(): void {
        if (self::$GF) return;
        self::$EXP = array_fill(0,512,0);
        self::$LOG = array_fill(0,256,0);
        $x=1;
        for ($i=0;$i<255;$i++){
            self::$EXP[$i]=$x; self::$LOG[$x]=$i;
            $x<<=1; if($x&0x100) $x^=0x11D;
        }
        for ($i=255;$i<512;$i++) self::$EXP[$i]=self::$EXP[$i-255];
        self::$GF=true;
    }
    private static function gfMul(int $a,int $b):int {
        return ($a===0||$b===0)?0:self::$EXP[(self::$LOG[$a]+self::$LOG[$b])%255];
    }
    private static function rsGen(int $n):array {
        $g=[1];
        for ($i=0;$i<$n;$i++){
            $ng=array_fill(0,count($g)+1,0);
            foreach ($g as $j=>$v){ $ng[$j]^=$v; $ng[$j+1]^=self::gfMul($v,self::$EXP[$i]); }
            $g=$ng;
        }
        return $g;
    }
    private static function rsEnc(array $d,int $n):array {
        $g=self::rsGen($n); $m=array_merge($d,array_fill(0,$n,0)); $l=count($d);
        for ($i=0;$i<$l;$i++){ $c=$m[$i]; if($c) for($j=1;$j<=$n;$j++) $m[$i+$j]^=self::gfMul($g[$j],$c); }
        return array_slice($m,$l);
    }

    // ── Encode ──────────────────────────────────────────────────────────────

    private static function dataCW(string $text,int $v):array {
        $bytes=array_values(unpack('C*',$text)); $n=count($bytes);
        [$ecpb,$g1n,$g1d,$g2n,$g2d]=self::$BLK[$v];
        $cap=($g1n*$g1d+$g2n*$g2d)*8;
        $bits='0100'.sprintf('%08b',$n);
        foreach ($bytes as $b) $bits.=sprintf('%08b',$b);
        $bits.=str_repeat('0',min(4,$cap-strlen($bits)));
        while (strlen($bits)%8) $bits.='0';
        $pads=['11101100','00010001']; $pi=0;
        while (strlen($bits)<$cap) $bits.=$pads[$pi++%2];
        $cw=[];
        for ($i=0;$i<$cap/8;$i++) $cw[]=(int)bindec(substr($bits,$i*8,8));
        return $cw;
    }

    private static function stream(string $text,int $v):string {
        [$ecpb,$g1n,$g1d,$g2n,$g2d]=self::$BLK[$v];
        $dc=self::dataCW($text,$v);
        $blocks=[]; $pos=0;
        for ($i=0;$i<$g1n;$i++){ $blocks[]=array_slice($dc,$pos,$g1d); $pos+=$g1d; }
        for ($i=0;$i<$g2n;$i++){ $blocks[]=array_slice($dc,$pos,$g2d); $pos+=$g2d; }
        $ec=array_map(fn($b)=>self::rsEnc($b,$ecpb),$blocks);
        $out=[]; $mx=max(array_map('count',$blocks));
        for ($i=0;$i<$mx;$i++) foreach ($blocks as $blk) if (isset($blk[$i])) $out[]=$blk[$i];
        for ($i=0;$i<$ecpb;$i++) foreach ($ec as $e) $out[]=$e[$i];
        $bits=''; foreach ($out as $cw) $bits.=sprintf('%08b',$cw);
        return $bits.str_repeat('0',self::$REM[$v-1]);
    }

    // ── Matrix ──────────────────────────────────────────────────────────────

    private static function finder(array &$m,int $r,int $c):void {
        static $p=[[1,1,1,1,1,1,1],[1,0,0,0,0,0,1],[1,0,1,1,1,0,1],
                   [1,0,1,1,1,0,1],[1,0,1,1,1,0,1],[1,0,0,0,0,0,1],[1,1,1,1,1,1,1]];
        for ($dr=0;$dr<7;$dr++) for ($dc=0;$dc<7;$dc++) $m[$r+$dr][$c+$dc]=$p[$dr][$dc];
    }
    private static function align(array &$m,int $r,int $c):void {
        static $p=[[1,1,1,1,1],[1,0,0,0,1],[1,0,1,0,1],[1,0,0,0,1],[1,1,1,1,1]];
        for ($dr=-2;$dr<=2;$dr++) for ($dc=-2;$dc<=2;$dc++) $m[$r+$dr][$c+$dc]=$p[$dr+2][$dc+2];
    }

    private static function funcMatrix(int $v):array {
        $s=$v*4+17; $m=array_fill(0,$s,array_fill(0,$s,-1));
        self::finder($m,0,0); self::finder($m,0,$s-7); self::finder($m,$s-7,0);
        for ($i=0;$i<8;$i++){
            $m[7][$i]=$m[$i][7]=0; $m[7][$s-1-$i]=$m[$i][$s-8]=0; $m[$s-8][$i]=$m[$s-1-$i][7]=0;
        }
        for ($i=8;$i<$s-8;$i++) $m[6][$i]=$m[$i][6]=($i%2===0)?1:0;
        $m[$s-8][8]=1; // dark module
        if (isset(self::$ALN[$v])) {
            $pos=self::$ALN[$v];
            foreach ($pos as $ar) foreach ($pos as $ac) {
                if ($ar<=8&&$ac<=8) continue; if ($ar<=8&&$ac>=$s-8) continue;
                if ($ar>=$s-8&&$ac<=8) continue;
                self::align($m,$ar,$ac);
            }
        }
        // Reserve format-info areas
        for ($i=0;$i<=8;$i++){ if ($m[8][$i]===-1) $m[8][$i]=0; if ($m[$i][8]===-1) $m[$i][8]=0; }
        for ($i=$s-8;$i<$s;$i++){ if ($m[8][$i]===-1) $m[8][$i]=0; if ($m[$i][8]===-1) $m[$i][8]=0; }
        return $m;
    }

    private static function placeData(array &$m,string $bits,int $v):void {
        $s=strlen($bits); $idx=0; $up=true; $col=$v*4+16;
        while ($col>=1){
            if ($col===6) $col--;
            $rows=$up?range($v*4+16,0,-1):range(0,$v*4+16);
            foreach ($rows as $row) for ($dx=0;$dx<=1;$dx++){
                $c=$col-$dx;
                if ($m[$row][$c]===-1) $m[$row][$c]=($idx<$s)?(int)$bits[$idx++]:0;
            }
            $col-=2; $up=!$up;
        }
    }

    private static function isFunc(int $sz,int $r,int $c,int $v):bool {
        if ($r<9&&$c<9) return true; if ($r<9&&$c>=$sz-8) return true;
        if ($r>=$sz-8&&$c<9) return true; if ($r===6||$c===6) return true;
        if ($r===$sz-8&&$c===8) return true;
        if (isset(self::$ALN[$v])) {
            $pos=self::$ALN[$v];
            foreach ($pos as $ar) foreach ($pos as $ac) {
                if ($ar<=8&&$ac<=8) continue; if ($ar<=8&&$ac>=$sz-8) continue;
                if ($ar>=$sz-8&&$ac<=8) continue;
                if (abs($r-$ar)<=2&&abs($c-$ac)<=2) return true;
            }
        }
        return false;
    }

    private static function maskBit(int $id,int $r,int $c):bool {
        return match($id){
            0=>($r+$c)%2===0, 1=>$r%2===0, 2=>$c%3===0,
            3=>($r+$c)%3===0, 4=>(intdiv($r,2)+intdiv($c,3))%2===0,
            5=>($r*$c)%2+($r*$c)%3===0,
            6=>(($r*$c)%2+($r*$c)%3)%2===0,
            7=>(($r+$c)%2+($r*$c)%3)%2===0, default=>false,
        };
    }

    private static function doMask(array &$m,int $mid,int $v):void {
        $s=$v*4+17;
        for ($r=0;$r<$s;$r++) for ($c=0;$c<$s;$c++)
            if ($m[$r][$c]!==-1&&!self::isFunc($s,$r,$c,$v)&&self::maskBit($mid,$r,$c)) $m[$r][$c]^=1;
    }

    private static function fmtInfo(int $mask):int {
        $d=$mask; $r=$d<<10; $g=0b10100110111;
        for ($i=4;$i>=0;$i--) if ($r&(1<<($i+10))) $r^=($g<<$i);
        return (($d<<10)|($r&0x3FF))^0b101010000010010;
    }

    private static function placeFmt(array &$m,int $mask,int $v):void {
        $s=$v*4+17; $f=self::fmtInfo($mask);
        $c1=[[8,0],[8,1],[8,2],[8,3],[8,4],[8,5],[8,7],[8,8],
             [7,8],[5,8],[4,8],[3,8],[2,8],[1,8],[0,8]];
        for ($i=0;$i<15;$i++) $m[$c1[$i][0]][$c1[$i][1]]=($f>>$i)&1;
        for ($i=0;$i<8;$i++) $m[8][$s-1-$i]=($f>>$i)&1;
        for ($i=8;$i<15;$i++) $m[$s-7+$i-8][8]=($f>>$i)&1;
        $m[$s-8][8]=1;
    }

    private static function penalty(array $m,int $s):int {
        $sc=0;
        for ($r=0;$r<$s;$r++) foreach ([true,false] as $h){
            $run=1;
            for ($i=1;$i<$s;$i++){
                $a=$h?$m[$r][$i-1]:$m[$i-1][$r]; $b=$h?$m[$r][$i]:$m[$i][$r];
                if ($a===$b){ $run++; if ($run===5) $sc+=3; elseif ($run>5) $sc++; } else $run=1;
            }
        }
        for ($r=0;$r<$s-1;$r++) for ($c=0;$c<$s-1;$c++){
            $v=$m[$r][$c];
            if ($v===$m[$r][$c+1]&&$v===$m[$r+1][$c]&&$v===$m[$r+1][$c+1]) $sc+=3;
        }
        $p1=[1,0,1,1,1,0,1,0,0,0,0]; $p2=[0,0,0,0,1,0,1,1,1,0,1];
        for ($r=0;$r<$s;$r++) for ($c=0;$c<=$s-11;$c++){
            $h1=$h2=$v1=$v2=true;
            for ($k=0;$k<11;$k++){
                if ($m[$r][$c+$k]!==$p1[$k]) $h1=false; if ($m[$r][$c+$k]!==$p2[$k]) $h2=false;
                if ($m[$c+$k][$r]!==$p1[$k]) $v1=false; if ($m[$c+$k][$r]!==$p2[$k]) $v2=false;
            }
            if ($h1||$v1) $sc+=40; if ($h2||$v2) $sc+=40;
        }
        $dark=0; foreach ($m as $row) foreach ($row as $v) if ($v===1) $dark++;
        $sc+=(int)(abs(floor($dark/($s*$s)*100/5)*5-50)/5)*10;
        return $sc;
    }

    // ── SVG render ──────────────────────────────────────────────────────────

    private static function svg(array $m,int $s,int $px):string {
        $q=4; $t=$s+2*$q; $d='';
        for ($r=0;$r<$s;$r++) for ($c=0;$c<$s;$c++)
            if (($m[$r][$c]??0)===1){ $x=$c+$q; $y=$r+$q; $d.="M$x,{$y}h1v1h-1Z"; }
        return '<svg xmlns="http://www.w3.org/2000/svg" width="'.$px.'" height="'.$px.'"'
              .' viewBox="0 0 '.$t.' '.$t.'" style="background:#fff">'
              .'<rect width="'.$t.'" height="'.$t.'" fill="#fff"/>'
              .'<path fill="#111827" d="'.$d.'"/></svg>';
    }

    // ── Public API ──────────────────────────────────────────────────────────

    /**
     * @throws RuntimeException if data > 214 bytes
     */
    public static function generate(string $data, int $px = 240): string {
        self::gfInit();
        $len=$v=null;
        $len=strlen($data);
        foreach (self::$CAP as $ver=>$cap) if ($len<=$cap){ $v=$ver; break; }
        if ($v===null) throw new \RuntimeException("QR: data too long ($len bytes, max 214)");
        $bits=self::stream($data,$v); $s=$v*4+17;
        $best=null; $bestSc=PHP_INT_MAX;
        for ($mid=0;$mid<8;$mid++){
            $m=self::funcMatrix($v);
            self::placeData($m,$bits,$v);
            self::doMask($m,$mid,$v);
            self::placeFmt($m,$mid,$v);
            $sc=self::penalty($m,$s);
            if ($sc<$bestSc){ $bestSc=$sc; $best=$m; }
        }
        return self::svg($best,$s,$px);
    }
}

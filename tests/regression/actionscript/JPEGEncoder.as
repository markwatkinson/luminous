package org.alivepdf.encoding
{
	import flash.display.BitmapData;
	import flash.filters.ColorMatrixFilter;
	import flash.geom.Point;
	import flash.utils.ByteArray;
	import org.alivepdf.encoding.BitString;
	import org.alivepdf.encoding.IntBlock;
	import org.alivepdf.encoding.IntList;
	/**
	 * Class that converts BitmapData into a valid JPEG
	 */
	public final class JPEGEncoder {

		// Static table initialization

		private static const ZigZagList:IntList = IntList.create([
			 0, 1, 5, 6,14,15,27,28,
			 2, 4, 7,13,16,26,29,42,
			 3, 8,12,17,25,30,41,43,
			 9,11,18,24,31,40,44,53,
			10,19,23,32,39,45,52,54,
			20,22,33,38,46,51,55,60,
			21,34,37,47,50,56,59,61,
			35,36,48,49,57,58,62,63
		]);

		private static const YQTList:IntList = IntList.create([
			16, 11, 10, 16, 24, 40, 51, 61,
			12, 12, 14, 19, 26, 58, 60, 55,
			14, 13, 16, 24, 40, 57, 69, 56,
			14, 17, 22, 29, 51, 87, 80, 62,
			18, 22, 37, 56, 68,109,103, 77,
			24, 35, 55, 64, 81,104,113, 92,
			49, 64, 78, 87,103,121,120,101,
			72, 92, 95, 98,112,100,103, 99
		]);
		private static const UVQTList:IntList = IntList.create([
			17, 18, 24, 47, 99, 99, 99, 99,
			18, 21, 26, 66, 99, 99, 99, 99,
			24, 26, 56, 99, 99, 99, 99, 99,
			47, 66, 99, 99, 99, 99, 99, 99,
			99, 99, 99, 99, 99, 99, 99, 99,
			99, 99, 99, 99, 99, 99, 99, 99,
			99, 99, 99, 99, 99, 99, 99, 99,
			99, 99, 99, 99, 99, 99, 99, 99
		]);
		private static const aasf:Array = [
			1.0, 1.387039845, 1.306562965, 1.175875602,
			1.0, 0.785694958, 0.541196100, 0.275899379
		];
		private static const aanscalesList:IntList = IntList.create([
			/* precomputed values scaled up by 14 bits */
			16384, 22725, 21407, 19266, 16384, 12873,  8867,  4520,
			22725, 31521, 29692, 26722, 22725, 17855, 12299,  6270,
			21407, 29692, 27969, 25172, 21407, 16819, 11585,  5906,
			19266, 26722, 25172, 22654, 19266, 15137, 10426,  5315,
			16384, 22725, 21407, 19266, 16384, 12873,  8867,  4520,
			12873, 17855, 16819, 15137, 12873, 10114,  6967,  3552,
			 8867, 12299, 11585, 10426,  8867,  6967,  4799,  2446,
			 4520,  6270,  5906,  5315,  4520,  3552,  2446,  1247
		]);

		private const YTable:Array = new Array(64);
		private const UVTable:Array = new Array(64);
		private const fdtbl_YList:IntList = IntList.create(new Array(64));
		private const fdtbl_UVList:IntList = IntList.create(new Array(64));

		private function initQuantTables(sf:int):void {
			var i:int;
			var t:int;
			var ZigZag:IntList = ZigZagList;
			var YQT:IntList = YQTList;
			for (i = 0; i < 64; ++i) {
				t = ((YQTList.data*sf+50)/100);
				YQT = YQT.next;
				if (t < 1) {
					t = 1;
				} else if (t > 255) {
					t = 255;
				}
				YTable[ZigZag.data] = t;
				ZigZag = ZigZag.next;
			}
			ZigZag = ZigZagList;
			var UVQT:IntList = UVQTList;
			for (i = 0; i < 64; ++i) {
				t = ((UVQT.data*sf+50)/100);
				UVQT = UVQT.next;
				if (t < 1) {
					t = 1;
				} else if (t > 255) {
					t = 255;
				}
				UVTable[ZigZag.data] = t;
				ZigZag = ZigZag.next;
			}
			ZigZag = ZigZagList;
			var fdtbl_Y:IntList = fdtbl_YList;
			var fdtbl_UV:IntList = fdtbl_UVList;
			for (i = 0; i < 64; ++i) {
				fdtbl_Y.data  =  YTable[ZigZag.data] << 3;
				fdtbl_UV.data = UVTable[ZigZag.data] << 3;
				ZigZag = ZigZag.next;
				fdtbl_Y = fdtbl_Y.next;
				fdtbl_UV = fdtbl_UV.next;
			}
		}

		private static const std_dc_luminance_nrcodesList:IntList = IntList.create([0,0,1,5,1,1,1,1,1,1,0,0,0,0,0,0,0]);
		private static const std_dc_luminance_valuesList:IntList = IntList.create([0,1,2,3,4,5,6,7,8,9,10,11]);
		private static const std_ac_luminance_nrcodesList:IntList = IntList.create([0,0,2,1,3,3,2,4,3,5,5,4,4,0,0,1,0x7d]);
		private static const std_ac_luminance_valuesList:IntList = IntList.create([
			0x01,0x02,0x03,0x00,0x04,0x11,0x05,0x12,
			0x21,0x31,0x41,0x06,0x13,0x51,0x61,0x07,
			0x22,0x71,0x14,0x32,0x81,0x91,0xa1,0x08,
			0x23,0x42,0xb1,0xc1,0x15,0x52,0xd1,0xf0,
			0x24,0x33,0x62,0x72,0x82,0x09,0x0a,0x16,
			0x17,0x18,0x19,0x1a,0x25,0x26,0x27,0x28,
			0x29,0x2a,0x34,0x35,0x36,0x37,0x38,0x39,
			0x3a,0x43,0x44,0x45,0x46,0x47,0x48,0x49,
			0x4a,0x53,0x54,0x55,0x56,0x57,0x58,0x59,
			0x5a,0x63,0x64,0x65,0x66,0x67,0x68,0x69,
			0x6a,0x73,0x74,0x75,0x76,0x77,0x78,0x79,
			0x7a,0x83,0x84,0x85,0x86,0x87,0x88,0x89,
			0x8a,0x92,0x93,0x94,0x95,0x96,0x97,0x98,
			0x99,0x9a,0xa2,0xa3,0xa4,0xa5,0xa6,0xa7,
			0xa8,0xa9,0xaa,0xb2,0xb3,0xb4,0xb5,0xb6,
			0xb7,0xb8,0xb9,0xba,0xc2,0xc3,0xc4,0xc5,
			0xc6,0xc7,0xc8,0xc9,0xca,0xd2,0xd3,0xd4,
			0xd5,0xd6,0xd7,0xd8,0xd9,0xda,0xe1,0xe2,
			0xe3,0xe4,0xe5,0xe6,0xe7,0xe8,0xe9,0xea,
			0xf1,0xf2,0xf3,0xf4,0xf5,0xf6,0xf7,0xf8,
			0xf9,0xfa
		]);

		private static const std_dc_chrominance_nrcodesList:IntList = IntList.create([0,0,3,1,1,1,1,1,1,1,1,1,0,0,0,0,0]);
		private static const std_dc_chrominance_valuesList:IntList = IntList.create([0,1,2,3,4,5,6,7,8,9,10,11]);
		private static const std_ac_chrominance_nrcodesList:IntList = IntList.create([0,0,2,1,2,4,4,3,4,7,5,4,4,0,1,2,0x77]);
		private static const std_ac_chrominance_valuesList:IntList = IntList.create([
			0x00,0x01,0x02,0x03,0x11,0x04,0x05,0x21,
			0x31,0x06,0x12,0x41,0x51,0x07,0x61,0x71,
			0x13,0x22,0x32,0x81,0x08,0x14,0x42,0x91,
			0xa1,0xb1,0xc1,0x09,0x23,0x33,0x52,0xf0,
			0x15,0x62,0x72,0xd1,0x0a,0x16,0x24,0x34,
			0xe1,0x25,0xf1,0x17,0x18,0x19,0x1a,0x26,
			0x27,0x28,0x29,0x2a,0x35,0x36,0x37,0x38,
			0x39,0x3a,0x43,0x44,0x45,0x46,0x47,0x48,
			0x49,0x4a,0x53,0x54,0x55,0x56,0x57,0x58,
			0x59,0x5a,0x63,0x64,0x65,0x66,0x67,0x68,
			0x69,0x6a,0x73,0x74,0x75,0x76,0x77,0x78,
			0x79,0x7a,0x82,0x83,0x84,0x85,0x86,0x87,
			0x88,0x89,0x8a,0x92,0x93,0x94,0x95,0x96,
			0x97,0x98,0x99,0x9a,0xa2,0xa3,0xa4,0xa5,
			0xa6,0xa7,0xa8,0xa9,0xaa,0xb2,0xb3,0xb4,
			0xb5,0xb6,0xb7,0xb8,0xb9,0xba,0xc2,0xc3,
			0xc4,0xc5,0xc6,0xc7,0xc8,0xc9,0xca,0xd2,
			0xd3,0xd4,0xd5,0xd6,0xd7,0xd8,0xd9,0xda,
			0xe2,0xe3,0xe4,0xe5,0xe6,0xe7,0xe8,0xe9,
			0xea,0xf2,0xf3,0xf4,0xf5,0xf6,0xf7,0xf8,
			0xf9,0xfa
		]);

		private function computeHuffmanTbl(nrcodesList:IntList, std_tableList:IntList):Array {
			var codevalue:int = 0;
			var nrcodes:IntList = nrcodesList.next;
			var std_table:IntList = std_tableList;
		//	var pos_in_table:int = 0;
			var HT:Array = new Array(251);
			for (var k:int = 1; k <= 16; ++k) {
				var nr:int = nrcodes.data;
				for (var j:int=1; j<=nr; ++j) {
					HT[std_table.data] = new BitString(codevalue, k);
					std_table = std_table.next;
		//			++pos_in_table;
					++codevalue;
				}
				nrcodes = nrcodes.next;
				codevalue<<=1;
			}
			return HT;
		}

		private var YDC_HT:Array;
		private var UVDC_HT:Array;
		private var YAC_HT:Array;
		private var UVAC_HT:Array;

		private function initHuffmanTbl():void {
			YDC_HT = computeHuffmanTbl(std_dc_luminance_nrcodesList,std_dc_luminance_valuesList);
			UVDC_HT = computeHuffmanTbl(std_dc_chrominance_nrcodesList,std_dc_chrominance_valuesList);
			YAC_HT = computeHuffmanTbl(std_ac_luminance_nrcodesList,std_ac_luminance_valuesList);
			UVAC_HT = computeHuffmanTbl(std_ac_chrominance_nrcodesList,std_ac_chrominance_valuesList);
		}

		private const bitcode:Array = new Array(65535);
		private const category:Array = new Array(65535);

		private function initCategoryNumber():void {
			var nrlower:int = 1;
			var nrupper:int = 2;
			var nr:int;
			var n:int;
			for (var cat:int=1; cat<=15; ++cat) {
				//Positive numbers
				for (nr=nrlower; nr<nrupper; ++nr) {
					n = 32767+nr;
					category[n] = cat;
					bitcode[n] = new BitString(nr, cat);
				}
				//Negative numbers
				for (nr=-(nrupper-1); nr<=-nrlower; ++nr) {
					n = 32767+nr;
					category[n] = cat;
					bitcode[n] = new BitString(nrupper-1+nr, cat);
				}
				nrlower <<= 1;
				nrupper <<= 1;
			}
		}

		// IO functions

		private var byteout:ByteArray;
		private var bytenew:int = 0;
		private var bytepos:int = 7;

		private function writeBits(bs:BitString):void {
			var value:int = bs.val;
			var posval:int = bs.len-1;
			while ( posval >= 0 ) {
				if (value & (1 << posval) ) {
					bytenew |= (1 << bytepos);
				}
				posval--;
				bytepos--;
				if (bytepos < 0) {
					if (bytenew == 0xFF) {
						writeByte(0xFF);
						writeByte(0);
					}
					else {
						writeByte(bytenew);
					}
					bytepos=7;
					bytenew=0;
				}
			}
		}

		private function writeByte(value:int):void {
			byteout.writeByte(value);
		}

		private function writeWord(value:int):void {
			writeByte((value>>8));
			writeByte((value   ));
		}

		// DCT & quantization core

//#define FIX_0_298631336  ((INT32)  2446)	/* FIX(0.298631336) */
//#define FIX_0_390180644  ((INT32)  3196)	/* FIX(0.390180644) */
//#define FIX_0_541196100  ((INT32)  4433)	/* FIX(0.541196100) */
//#define FIX_0_765366865  ((INT32)  6270)	/* FIX(0.765366865) */
//#define FIX_0_899976223  ((INT32)  7373)	/* FIX(0.899976223) */
//#define FIX_1_175875602  ((INT32)  9633)	/* FIX(1.175875602) */
//#define FIX_1_501321110  ((INT32)  12299)	/* FIX(1.501321110) */
//#define FIX_1_847759065  ((INT32)  15137)	/* FIX(1.847759065) */
//#define FIX_1_961570560  ((INT32)  16069)	/* FIX(1.961570560) */
//#define FIX_2_053119869  ((INT32)  16819)	/* FIX(2.053119869) */
//#define FIX_2_562915447  ((INT32)  20995)	/* FIX(2.562915447) */
//#define FIX_3_072711026  ((INT32)  25172)	/* FIX(3.072711026) */
		private function fDCTQuant(data:IntBlock, fdtbl:IntList):IntBlock {
			var tmp0:int, tmp1:int, tmp2:int, tmp3:int, tmp4:int, tmp5:int, tmp6:int, tmp7:int;
			var tmp10:int, tmp11:int, tmp12:int, tmp13:int;
			var d0:int, d1:int, d2:int, d3:int, d4:int, d5:int, d6:int, d7:int;
			var z1:int, z2:int, z3:int, z4:int, z5:int;
			var i:int;
			var row:IntBlock, col:IntBlock;
			var dataOff:IntBlock;
			/* Pass 1: process rows. */
			/* Note results are scaled up by sqrt(8) compared to a true DCT; */
			/* furthermore, we scale the results by 2**2. */
			row = data;
			for (i=0; i<8; ++i) {
				dataOff = row;
				d0 = dataOff.data;
				dataOff = dataOff.next;
				d1 = dataOff.data;
				dataOff = dataOff.next;
				d2 = dataOff.data;
				dataOff = dataOff.next;
				d3 = dataOff.data;
				dataOff = dataOff.next;
				d4 = dataOff.data;
				dataOff = dataOff.next;
				d5 = dataOff.data;
				dataOff = dataOff.next;
				d6 = dataOff.data;
				dataOff = dataOff.next;
				d7 = dataOff.data;

				tmp0 = d0+d7;
				tmp7 = d0-d7;
				tmp1 = d1+d6;
				tmp6 = d1-d6;
				tmp2 = d2+d5;
				tmp5 = d2-d5;
				tmp3 = d3+d4;
				tmp4 = d3-d4;

				/* Even part per LL&M figure 1 --- note that published figure is faulty;
				 * rotator "sqrt(2)*c1" should be "sqrt(2)*c6".
				 */
				tmp10 = tmp0 + tmp3;
				tmp13 = tmp0 - tmp3;
				tmp11 = tmp1 + tmp2;
				tmp12 = tmp1 - tmp2;

				z1 = ((tmp12 + tmp13) * /*FIX_0_541196100*/4433);

				dataOff = row;
				dataOff.data = (tmp10 + tmp11) << 2;
				dataOff = dataOff.next.next;
				dataOff.data = (z1 + tmp13 * /*FIX_0_765366865*/6270 + (/*1 << 10*/0x400)) >> 11;
				dataOff = dataOff.next.next;
				dataOff.data = (tmp10 - tmp11) << 2;
				dataOff = dataOff.next.next;
				dataOff.data = (z1 - tmp12 * /*FIX_1_847759065*/15137 + (/*1 << 10*/0x400)) >> 11;

				/* Odd part per figure 8 --- note paper omits factor of sqrt(2).
				 * cK represents cos(K*pi/16).
				 * i0..i3 in the paper are tmp4..tmp7 here.
				 */
				z1 = tmp4 + tmp7;
				z2 = tmp5 + tmp6;
				z3 = tmp4 + tmp6;
				z4 = tmp5 + tmp7;
				z5 = (z3 + z4) * /*FIX_1_175875602*/9633; /* sqrt(2) * c3 */

				tmp4 = tmp4 * /*FIX_0_298631336*/2446; /* sqrt(2) * (-c1+c3+c5-c7) */
				tmp5 = tmp5 * /*FIX_2_053119869*/16819; /* sqrt(2) * ( c1+c3-c5+c7) */
				tmp6 = tmp6 * /*FIX_3_072711026*/25172; /* sqrt(2) * ( c1+c3+c5-c7) */
				tmp7 = tmp7 * /*FIX_1_501321110*/12299; /* sqrt(2) * ( c1+c3-c5-c7) */
				z1 = - z1 * /*FIX_0_899976223*/7373; /* sqrt(2) * (c7-c3) */
				z2 = - z2 * /*FIX_2_562915447*/20995; /* sqrt(2) * (-c1-c3) */
				z3 = - z3 * /*FIX_1_961570560*/16069; /* sqrt(2) * (-c3-c5) */
				z4 = - z4 * /*FIX_0_390180644*/3196; /* sqrt(2) * (c5-c3) */

				z3 += z5;
				z4 += z5;

				dataOff = row.next;
				dataOff.data = (tmp7 + z1 + z4 + (/*1 << 10*/0x400)) >> 11;
				dataOff = dataOff.next.next;
				dataOff.data = (tmp6 + z2 + z3 + (/*1 << 10*/0x400)) >> 11;
				dataOff = dataOff.next.next;
				dataOff.data = (tmp5 + z2 + z4 + (/*1 << 10*/0x400)) >> 11;
				dataOff = dataOff.next.next;
				dataOff.data = (tmp4 + z1 + z3 + (/*1 << 10*/0x400)) >> 11;

				row = row.down; /* advance pointer to next row */
			}

			/* Pass 2: process columns.
			 * We remove the PASS1_BITS scaling, but leave the results scaled up
			 * by an overall factor of 8.
			 */
			col = data;
			for (i=0; i<8; ++i) {
				dataOff = col;
				d0 = dataOff.data;
				dataOff = dataOff.down;
				d1 = dataOff.data;
				dataOff = dataOff.down;
				d2 = dataOff.data;
				dataOff = dataOff.down;
				d3 = dataOff.data;
				dataOff = dataOff.down;
				d4 = dataOff.data;
				dataOff = dataOff.down;
				d5 = dataOff.data;
				dataOff = dataOff.down;
				d6 = dataOff.data;
				dataOff = dataOff.down;
				d7 = dataOff.data;

				tmp0 = d0+d7;
				tmp7 = d0-d7;
				tmp1 = d1+d6;
				tmp6 = d1-d6;
				tmp2 = d2+d5;
				tmp5 = d2-d5;
				tmp3 = d3+d4;
				tmp4 = d3-d4;

				/* Even part per LL&M figure 1 --- note that published figure is faulty;
				 * rotator "sqrt(2)*c1" should be "sqrt(2)*c6".
				 */
				tmp10 = tmp0 + tmp3;
				tmp13 = tmp0 - tmp3;
				tmp11 = tmp1 + tmp2;
				tmp12 = tmp1 - tmp2;

				z1 = ((tmp12 + tmp13) * /*FIX_0_541196100*/4433);

				dataOff = col;
				dataOff.data = (tmp10 + tmp11 + (/*1 << 1*/0x2)) >> 2;
				dataOff = dataOff.down.down;
				dataOff.data = (z1 + tmp13 * /*FIX_0_765366865*/6270 + (/*1 << 14*/0x4000)) >> 15;
				dataOff = dataOff.down.down;
				dataOff.data = (tmp10 - tmp11 + (/*1 << 1*/0x2)) >> 2;
				dataOff = dataOff.down.down;
				dataOff.data = (z1 - tmp12 * /*FIX_1_847759065*/15137 + (/*1 << 14*/0x4000)) >> 15;

				/* Odd part per figure 8 --- note paper omits factor of sqrt(2).
				 * cK represents cos(K*pi/16).
				 * i0..i3 in the paper are tmp4..tmp7 here.
				 */
				z1 = tmp4 + tmp7;
				z2 = tmp5 + tmp6;
				z3 = tmp4 + tmp6;
				z4 = tmp5 + tmp7;
				z5 = (z3 + z4) * /*FIX_1_175875602*/9633; /* sqrt(2) * c3 */

				tmp4 = tmp4 * /*FIX_0_298631336*/2446; /* sqrt(2) * (-c1+c3+c5-c7) */
				tmp5 = tmp5 * /*FIX_2_053119869*/16819; /* sqrt(2) * ( c1+c3-c5+c7) */
				tmp6 = tmp6 * /*FIX_3_072711026*/25172; /* sqrt(2) * ( c1+c3+c5-c7) */
				tmp7 = tmp7 * /*FIX_1_501321110*/12299; /* sqrt(2) * ( c1+c3-c5-c7) */
				z1 = - z1 * /*FIX_0_899976223*/7373; /* sqrt(2) * (c7-c3) */
				z2 = - z2 * /*FIX_2_562915447*/20995; /* sqrt(2) * (-c1-c3) */
				z3 = - z3 * /*FIX_1_961570560*/16069; /* sqrt(2) * (-c3-c5) */
				z4 = - z4 * /*FIX_0_390180644*/3196; /* sqrt(2) * (c5-c3) */

				z3 += z5;
				z4 += z5;

				dataOff = col.down;
				dataOff.data = (tmp7 + z1 + z4 + (/*1 << 14*/0x4000)) >> 15;
				dataOff = dataOff.down.down;
				dataOff.data = (tmp6 + z2 + z3 + (/*1 << 14*/0x4000)) >> 15;
				dataOff = dataOff.down.down;
				dataOff.data = (tmp5 + z2 + z4 + (/*1 << 14*/0x4000)) >> 15;
				dataOff = dataOff.down.down;
				dataOff.data = (tmp4 + z1 + z3 + (/*1 << 14*/0x4000)) >> 15;

				col = col.next; /* advance pointer to next column */
			}

			// Quantize/descale the coefficients
			dataOff = data;
			for (i=0; i<64; ++i) {
				// Apply the quantization and scaling factor & Round to nearest integer
				var qval:int = fdtbl.data;
				fdtbl = fdtbl.next;
				var temp:int = dataOff.data;
				if (temp < 0) {
					temp = -temp;
					temp += qval >> 1;	/* for rounding */
					if (temp >= qval) temp /= qval;
					else temp = 0;
					temp = -temp;
				} else {
					temp += qval >> 1;	/* for rounding */
					if (temp >= qval) temp /= qval;
					else temp = 0;
				}
				dataOff.data = temp;
				dataOff = dataOff.next;
			}
			return data;
		}

		// Chunk writing

		private function writeAPP0():void {
			writeWord(0xFFE0); // marker
			writeWord(16); // length
			writeByte(0x4A); // J
			writeByte(0x46); // F
			writeByte(0x49); // I
			writeByte(0x46); // F
			writeByte(0); // = "JFIF",'\0'
			writeByte(1); // versionhi
			writeByte(1); // versionlo
			writeByte(0); // xyunits
			writeWord(1); // xdensity
			writeWord(1); // ydensity
			writeByte(0); // thumbnwidth
			writeByte(0); // thumbnheight
		}

		private function writeSOF0(width:int, height:int):void {
			writeWord(0xFFC0); // marker
			writeWord(17);   // length, truecolor YUV JPG
			writeByte(8);    // precision
			writeWord(height);
			writeWord(width);
			writeByte(3);    // nrofcomponents
			writeByte(1);    // IdY
			writeByte(0x11); // HVY
			writeByte(0);    // QTY
			writeByte(2);    // IdU
			writeByte(0x11); // HVU
			writeByte(1);    // QTU
			writeByte(3);    // IdV
			writeByte(0x11); // HVV
			writeByte(1);    // QTV
		}

		private function writeDQT():void {
			writeWord(0xFFDB); // marker
			writeWord(132);	   // length
			writeByte(0);
			var i:int;
			for (i=0; i<64; ++i) {
				writeByte(YTable[i]);
			}
			writeByte(1);
			for (i=0; i<64; ++i) {
				writeByte(UVTable[i]);
			}
		}

		private function writeDHT():void {
			writeWord(0xFFC4); // marker
			writeWord(0x01A2); // length
			var i:int;

			writeByte(0); // HTYDCinfo
			var std_dc_luminance_nrcodes:IntList = std_dc_luminance_nrcodesList.next;
			for (i=1; i<=16; ++i) {
				writeByte(std_dc_luminance_nrcodes.data);
				std_dc_luminance_nrcodes = std_dc_luminance_nrcodes.next;
			}
			var std_dc_luminance_values:IntList = std_dc_luminance_valuesList;
			for (i=0; i<=11; ++i) {
				writeByte(std_dc_luminance_values.data);
				std_dc_luminance_values = std_dc_luminance_values.next;
			}

			writeByte(0x10); // HTYACinfo
			var std_ac_luminance_nrcodes:IntList = std_ac_luminance_nrcodesList.next;
			for (i=1; i<=16; ++i) {
				writeByte(std_ac_luminance_nrcodes.data);
				std_ac_luminance_nrcodes = std_ac_luminance_nrcodes.next;
			}
			var std_ac_luminance_values:IntList = std_ac_luminance_valuesList;
			for (i=0; i<=161; ++i) {
				writeByte(std_ac_luminance_values.data);
				std_ac_luminance_values = std_ac_luminance_values.next;
			}

			writeByte(1); // HTUDCinfo
			var std_dc_chrominance_nrcodes:IntList = std_dc_chrominance_nrcodesList.next;
			for (i=1; i<=16; ++i) {
				writeByte(std_dc_chrominance_nrcodes.data);
				std_dc_chrominance_nrcodes = std_dc_chrominance_nrcodes.next;
			}
			var std_dc_chrominance_values:IntList = std_dc_chrominance_valuesList;
			for (i=0; i<=11; ++i) {
				writeByte(std_dc_chrominance_values.data);
				std_dc_chrominance_values = std_dc_chrominance_values.next;
			}

			writeByte(0x11); // HTUACinfo
			var std_ac_chrominance_nrcodes:IntList = std_ac_chrominance_nrcodesList.next;
			for (i=1; i<=16; ++i) {
				writeByte(std_ac_chrominance_nrcodes.data);
				std_ac_chrominance_nrcodes = std_ac_chrominance_nrcodes.next;
			}
			var std_ac_chrominance_values:IntList = std_ac_chrominance_valuesList;
			for (i=0; i<=161; ++i) {
				writeByte(std_ac_chrominance_values.data);
				std_ac_chrominance_values = std_ac_chrominance_values.next;
			}
		}

		private function writeSOS():void {
			writeWord(0xFFDA); // marker
			writeWord(12); // length
			writeByte(3); // nrofcomponents
			writeByte(1); // IdY
			writeByte(0); // HTY
			writeByte(2); // IdU
			writeByte(0x11); // HTU
			writeByte(3); // IdV
			writeByte(0x11); // HTV
			writeByte(0); // Ss
			writeByte(0x3f); // Se
			writeByte(0); // Bf
		}

		// Core processing
		private const DU:Array = new Array(64);

		private function processDU(CDU:IntBlock, fdtbl:IntList, DC:int, HTDC:Array, HTAC:Array):int {
			var EOB:BitString = HTAC[0x00];
			var M16zeroes:BitString = HTAC[0xF0];
			var i:int;

			var DU_DCT:IntBlock = fDCTQuant(CDU, fdtbl);
			//ZigZag reorder
			var ZigZag:IntList = ZigZagList;
			for (i=0;i<64;++i) {
				DU[ZigZag.data] = DU_DCT.data;
				ZigZag = ZigZag.next;
				DU_DCT = DU_DCT.next;
			}
			var Diff:int = DU[0] - DC; DC = DU[0];
			//Encode DC
			if (Diff==0) {
				writeBits(HTDC[0]); // Diff might be 0
			} else {
				i = 32767+Diff;
				writeBits(HTDC[category[i]>>0]);
				writeBits(bitcode[i]);
			}
			//Encode ACs
			var end0pos:int = 63;
			while((end0pos>0)&&(DU[end0pos]==0)) --end0pos;
			//end0pos = first element in reverse order !=0
			if ( end0pos == 0) {
				writeBits(EOB);
				return DC;
			}
			i = 1;
			while ( i <= end0pos ) {
				var startpos:int = i;
				while((DU[i]==0) && (i<=end0pos)) ++i;
				var nrzeroes:int = i-startpos;
				var n:int;
				if ( nrzeroes >= 16 ) {
					n = nrzeroes/16;
					for (var nrmarker:int=1; nrmarker <= n; ++nrmarker) {
						writeBits(M16zeroes);
					}
					nrzeroes = (nrzeroes&0xF);
				}
				n = 32767+DU[i];
				writeBits(HTAC[((nrzeroes<<4)+category[n])>>0]);
				writeBits(bitcode[n]);
				++i;
			}
			if ( end0pos != 63 ) {
				writeBits(EOB);
			}
			return DC;
		}

		private const YDUBlock:IntBlock = IntBlock.create8_8(new Array(64));
		private const UDUBlock:IntBlock = IntBlock.create8_8(new Array(64));
		private const VDUBlock:IntBlock = IntBlock.create8_8(new Array(64));
		private static const fltrRGB2YUV:ColorMatrixFilter = new ColorMatrixFilter([
			 0.29900,  0.58700,  0.11400, 0,   0,
			-0.16874, -0.33126,  0.50000, 0, 128,
			 0.50000, -0.41869, -0.08131, 0, 128,
			       0,        0,        0, 1,   0
		]);
		private static const orgn:Point = new Point();

		//private static const rgb_ycc_tab:Array = new Array(2048);
		//private function init_rgb_ycc_tab():void {
		//	for (var i:int = 0; i <= 255; i++) {
		//		rgb_ycc_tab[i]      =  19595 * i;
		//		rgb_ycc_tab[(i+ 256)>>0] =  38470 * i;
		//		rgb_ycc_tab[(i+ 512)>>0] =   7471 * i + 0x8000;
		//		rgb_ycc_tab[(i+ 768)>>0] = -11059 * i;
		//		rgb_ycc_tab[(i+1024)>>0] = -21709 * i;
				/* We use a rounding fudge-factor of 0.5-epsilon for Cb and Cr.
				 * This ensures that the maximum output will round to MAXJSAMPLE
				 * not MAXJSAMPLE+1, and thus that we don't have to range-limit.
				 */
		//		rgb_ycc_tab[(i+1280)>>0] =  32768 * i + 0x807FFF;
				/*  B=>Cb and R=>Cr tables are the same
				    rgb_ycc_tab[i+R_CR_OFF] = FIX(0.50000) * i    + CBCR_OFFSET + ONE_HALF-1;
				*/
		//		rgb_ycc_tab[(i+1536)>>0] = -27439 * i;
		//		rgb_ycc_tab[(i+1792)>>0] = - 5329 * i;
		//	}
		//}

		private function RGB2YUV(img:BitmapData, xpos:int, ypos:int):void {
			var YDU:IntBlock = YDUBlock;
			var UDU:IntBlock = UDUBlock;
			var VDU:IntBlock = VDUBlock;
		//	var pos:int=0;
			for (var y:int=0; y<8; ++y) {
				for (var x:int=0; x<8; ++x) {
					var P:int = img.getPixel(xpos+x,ypos+y);
					var R:int = ((P>>16)&0xFF);
					var G:int = ((P>> 8)&0xFF);
					var B:int = ((P    )&0xFF);
					/* RGB2YUV with ColorMatrixFilter */
					YDU.data = R-128;
					UDU.data = G-128;
					VDU.data = B-128;
					/* float RGB2YUV without ColorMatrixFilter
					YDU[pos] = ((( 0.29900) * R + ( 0.58700) * G + ( 0.11400) * B)) - 128;
					UDU[pos] = ((( -0.16874) * R + ( -0.33126) * G + ( 0.50000) * B));
					VDU[pos] = ((( 0.50000) * R + ( -0.41869) * G + ( -0.08131) * B));
					*/
					/* precalculated RGB2YUV without ColorMatrixFilter
					YDU[pos] = ((rgb_ycc_tab[R]             + rgb_ycc_tab[(G +  256)>>0] + rgb_ycc_tab[(B +  512)>>0]) >> 16)-128;
					UDU[pos] = ((rgb_ycc_tab[(R +  768)>>0] + rgb_ycc_tab[(G + 1024)>>0] + rgb_ycc_tab[(B + 1280)>>0]) >> 16)-128;
					VDU[pos] = ((rgb_ycc_tab[(R + 1280)>>0] + rgb_ycc_tab[(G + 1536)>>0] + rgb_ycc_tab[(B + 1792)>>0]) >> 16)-128;
					*/
					YDU = YDU.next;
					UDU = UDU.next;
					VDU = VDU.next;
		//			++pos;
				}
			}
		}

		/**
		 * Constructor for JPEGEncoder class
		 *
		 * @param quality The quality level between 1 and 100 that detrmines the
		 * level of compression used in the generated JPEG
		 * @param dct The forward DCT method to use,
		 * supported methods: JDCT_ISLOW, JDCT_IFAST, JDCT_FLOAT
		 * @langversion ActionScript 3.0
		 * @playerversion Flash 9.0
		 * @tiptext
		 */
		public function JPEGEncoder(quality:Number = 50) {
			if (quality <= 0) {
				quality = 1;
			}
			if (quality > 100) {
				quality = 100;
			}
			var sf:int = 0;
			if (quality < 50) {
				sf = (5000 / quality);
			} else {
				sf = (200 - quality*2);
			}
			// Create tables
			initHuffmanTbl();
			initCategoryNumber();
			initQuantTables(sf);
			//init_rgb_ycc_tab();
		}

		/**
		 * Created a JPEG image from the specified BitmapData
		 *
		 * @param image The BitmapData that will be converted into the JPEG format.
		 * @return a ByteArray representing the JPEG encoded image data.
		 * @langversion ActionScript 3.0
		 * @playerversion Flash 9.0
		 * @tiptext
		 */
		public function encode(image:BitmapData):ByteArray {
			//var img:BitmapData = image;
			var img:BitmapData = image.clone();
			img.applyFilter(img, img.rect, orgn, fltrRGB2YUV);
			var height:int = img.height;
			var width:int = img.width;

			// Initialize bit writer
			byteout = new ByteArray();
			bytenew=0;
			bytepos=7;

			// Add JPEG headers
			writeWord(0xFFD8); // SOI
			writeAPP0();
			writeDQT();
			writeSOF0(width,height);
			writeDHT();
			writeSOS();

			// Encode 8x8 macroblocks
			var DCY:int=0;
			var DCU:int=0;
			var DCV:int=0;

			for (var ypos:int=0; ypos<height; ypos+=8) {
				for (var xpos:int=0; xpos<width; xpos+=8) {
					RGB2YUV(img, xpos, ypos);
					DCY = processDU(YDUBlock, fdtbl_YList,  DCY,  YDC_HT,  YAC_HT);
					DCU = processDU(UDUBlock, fdtbl_UVList, DCU, UVDC_HT, UVAC_HT);
					DCV = processDU(VDUBlock, fdtbl_UVList, DCV, UVDC_HT, UVAC_HT);
				}
			}

			img.dispose();

			// Do the bit alignment of the EOI marker
			if ( bytepos >= 0 ) {
				writeBits(new BitString((1<<(bytepos+1))-1, bytepos+1));
			}

			writeWord(0xFFD9); //EOI
			return byteout;
		}
	}
}

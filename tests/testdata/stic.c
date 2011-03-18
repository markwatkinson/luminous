/* ======================================================================== */
/*  STIC.C -- New, complete, hopefully fast STIC implementation.            */
/* ======================================================================== */

#include "config.h"
#include "periph/periph.h"
#include "mem/mem.h"
#include "cp1600/cp1600.h"
#include "demo/demo.h"
#include "gfx/gfx.h"
#include "stic.h"
#include "speed/speed.h"
#include "debug/debug_.h"


static const char rcs_id[] UNUSED = "$Id$";

LOCAL void stic_draw_fgbg(stic_t *stic);
LOCAL void stic_draw_cstk(stic_t *stic);


#ifdef __GNUC__
#define ALIGN __attribute__((aligned(128)))
#else
#define ALIGN 
#endif

/* ======================================================================== */
/*  STIC Register Masks                                                     */
/*  Only certain bits in each STIC register are writeable.  The bits that   */
/*  are not implemented return a fixed pattern of 0s and 1s.  The table     */
/*  below encodes this information in the form of an "AND / OR" mask pair.  */
/*  The data is first ANDed with the AND mask.  This mask effectively       */
/*  indicates the implemented bits.  The data is then ORed with the OR      */
/*  mask.  This second mask effectively indicates which of the bits always  */
/*  read as 1.                                                              */
/* ======================================================================== */
struct stic_reg_mask_t
{
    uint_32 and_mask;
    uint_32 or_mask;
};

LOCAL const struct stic_reg_mask_t stic_reg_mask[0x40] ALIGN =
{
    /* MOB X Registers                                  0x00 - 0x07 */
    {0x07FF,0x3800}, {0x07FF,0x3800}, {0x07FF,0x3800}, {0x07FF,0x3800},
    {0x07FF,0x3800}, {0x07FF,0x3800}, {0x07FF,0x3800}, {0x07FF,0x3800},

    /* MOB Y Registers                                  0x08 - 0x0F */
    {0x0FFF,0x3000}, {0x0FFF,0x3000}, {0x0FFF,0x3000}, {0x0FFF,0x3000},
    {0x0FFF,0x3000}, {0x0FFF,0x3000}, {0x0FFF,0x3000}, {0x0FFF,0x3000},

    /* MOB A Registers                                  0x10 - 0x17 */
    {0x3FFF,0x0000}, {0x3FFF,0x0000}, {0x3FFF,0x0000}, {0x3FFF,0x0000},
    {0x3FFF,0x0000}, {0x3FFF,0x0000}, {0x3FFF,0x0000}, {0x3FFF,0x0000},

    /* MOB C Registers                                  0x18 - 0x1F */
    {0x03FE,0x3C00}, {0x03FD,0x3C00}, {0x03FB,0x3C00}, {0x03F7,0x3C00},
    {0x03EF,0x3C00}, {0x03DF,0x3C00}, {0x03BF,0x3C00}, {0x037F,0x3C00},

    /* Display enable, Mode select                      0x20 - 0x21 */
    {0x0000,0x3FFF}, {0x0000,0x3FFF}, 

    /* Unimplemented registers                          0x22 - 0x27 */
    {0x0000,0x3FFF}, {0x0000,0x3FFF}, {0x0000,0x3FFF}, {0x0000,0x3FFF},
    {0x0000,0x3FFF}, {0x0000,0x3FFF},

    /* Color stack, border color                        0x28 - 0x2C */
    {0x000F,0x3FF0}, {0x000F,0x3FF0}, {0x000F,0x3FF0}, {0x000F,0x3FF0}, 
    {0x000F,0x3FF0}, 

    /* Unimplemented registers                          0x2D - 0x2F */
    {0x0000,0x3FFF}, {0x0000,0x3FFF}, {0x0000,0x3FFF},

    /* Horiz delay, vertical delay, border extension    0x30 - 0x32 */
    {0x0007,0x3FF8}, {0x0007,0x3FF8}, {0x0003,0x3FFC},

    /* Unimplemented registers                          0x33 - 0x3F */
    {0x0000,0x3FFF},
    {0x0000,0x3FFF}, {0x0000,0x3FFF}, {0x0000,0x3FFF}, {0x0000,0x3FFF},
    {0x0000,0x3FFF}, {0x0000,0x3FFF}, {0x0000,0x3FFF}, {0x0000,0x3FFF},
    {0x0000,0x3FFF}, {0x0000,0x3FFF}, {0x0000,0x3FFF}, {0x0000,0x3FFF}
};

/* ======================================================================== */
/*  MOB height secret decoder ring.                                         */
/* ======================================================================== */
LOCAL const int stic_mob_hgt[8] = { 8, 16, 16, 32, 32, 64, 64, 128 };

/* ======================================================================== */
/*  STIC Color Nibble Masks -- For generating packed-nibble pixels.         */
/* ======================================================================== */
LOCAL const uint_32 stic_color_mask[16] ALIGN =
{
    0x00000000, 0x11111111, 0x22222222, 0x33333333,
    0x44444444, 0x55555555, 0x66666666, 0x77777777,
    0x88888888, 0x99999999, 0xAAAAAAAA, 0xBBBBBBBB,
    0xCCCCCCCC, 0xDDDDDDDD, 0xEEEEEEEE, 0xFFFFFFFF
};

/* ======================================================================== */
/*  STIC Color Mask and Bit Manipulation Lookup Tables                      */
/*   -- b2n expands 8 bits to 8 nibbles.                                    */
/*   -- b2n_d expands 8 bits to 4 nibbles, pixel-doubling as it goes.       */
/*   -- b2n_r expands 8 bits to 4 nibbles, reversing bit order as it goes.  */
/*   -- b2n_rd expands 8 bits to 4 nibbles, reversing and pixel-doubleling. */
/*   -- n2b expands 2 nibbles to 2 bytes.                                   */
/*   -- bit_r reverses the bit order in an 8-bit byte.                      */
/*   -- bit_d doubles the bits in an 8-bit byte to a 16-bit int.            */
/*   -- bit_rd doubles the bits and reverses them.                          */
/*  These are computed at runtime for now.                                  */
/* ======================================================================== */
LOCAL uint_16 stic_n2b  [256] ALIGN;
LOCAL uint_32 stic_b2n  [256] ALIGN, stic_b2n_r [256] ALIGN; 
LOCAL uint_32 stic_b2n_d[16]  ALIGN, stic_b2n_rd[16]  ALIGN; 
LOCAL uint_16 stic_bit  [256] ALIGN, stic_bit_r [256] ALIGN;
LOCAL uint_16 stic_bit_d[256] ALIGN, stic_bit_rd[256] ALIGN;


/* ======================================================================== */
/*  STIC_CTRL_RD -- Read from a STIC control register (addr <= 0x3F)        */
/* ======================================================================== */
uint_32 stic_ctrl_rd(periph_t *per, periph_t *req, uint_32 addr, uint_32 data)
{
    stic_t *stic = (stic_t *)per->parent;
    uint_64 access_time = req && req->req ? req->req->now + 4 : 0;

    (void)data;

#if 1
    /* -------------------------------------------------------------------- */
    /*  Is this access after the Bus-Copy -> Bus-Isolation transition       */
    /*  or before the Bus-Isolation -> Bus-Copy transition?                 */
    /* -------------------------------------------------------------------- */
    if (access_time > stic->stic_accessible ||
        access_time < stic->req_bus->intak)
    {
        /* ---------------------------------------------------------------- */
        /*  Yes:  Return garbage.                                           */
        /* ---------------------------------------------------------------- */
        return addr < 0x80 ? 0x000E & addr : 0xFFFF;
    }
#endif

    /* -------------------------------------------------------------------- */
    /*  If reading location 0x21, put the display into Color-Stack mode.    */
    /* -------------------------------------------------------------------- */
    if ((addr & 0x7F) == 0x0021)
    {
        if (stic->mode != 0) stic->bt_dirty = 1;
        stic->mode = 0;
//jzp_printf("COLORSTACK\n");
        stic->upd  = stic_draw_cstk;
    }

    /* -------------------------------------------------------------------- */
    /*  If we're accessing a STIC CR alias, just return 0xFFFF.             */
    /* -------------------------------------------------------------------- */
    if (addr >= 0x4000)
        return 0xFFFF;

    /* -------------------------------------------------------------------- */
    /*  If we're reading 0x40-0x7F, just sample GRAM and return.            */
    /* -------------------------------------------------------------------- */
    if (addr >= 0x0040)
        return stic->gmem[addr + 0x800] & 0xFF;

    /* -------------------------------------------------------------------- */
    /*  Now just return the raw value from our internal register file,      */
    /*  appropriately conditioned by the read/write masks.                  */
    /* -------------------------------------------------------------------- */
    return (stic->raw[addr] & stic_reg_mask[addr].and_mask) |
            stic_reg_mask[addr].or_mask;
}

/* ======================================================================== */
/*  STIC_CTRL_PEEK -- Like read, except w/out side effects or restrictions  */
/* ======================================================================== */
uint_32 stic_ctrl_peek(periph_t *per, periph_t *req, uint_32 addr, uint_32 data)
{
    stic_t *stic = (stic_t *)per->parent;

    (void)req;
    (void)data;

    if (addr > 0x40)
        return 0xFFFF;

    /* -------------------------------------------------------------------- */
    /*  Just return the raw value from our internal register file,          */
    /*  appropriately conditioned by the read/write masks.                  */
    /* -------------------------------------------------------------------- */
    return (stic->raw[addr] & stic_reg_mask[addr].and_mask) |
            stic_reg_mask[addr].or_mask;
}

/* ======================================================================== */
/*  STIC_CTRL_WR -- Write to a STIC control register (addr <= 0x3F)         */
/* ======================================================================== */
void stic_ctrl_wr(periph_t *per, periph_t *req, uint_32 addr, uint_32 data)
{
    stic_t *stic = (stic_t *)per->parent;
    uint_64 access_time = req && req->req ? req->req->now + 4 : 0;
    uint_32 old = 0;

    addr &= 0x7F;

    /* -------------------------------------------------------------------- */
    /*  Ignore writes to the strange GROM visibility window at $40 and up.  */
    /* -------------------------------------------------------------------- */
    if (addr >= 0x40)
        return;

#if 1
    /* -------------------------------------------------------------------- */
    /*  Is this access after the Bus-Copy -> Bus-Isolation transition       */
    /*  or before the Bus-Isolation -> Bus-Copy transition?                 */
    /* -------------------------------------------------------------------- */
    if (access_time > stic->stic_accessible || 
        access_time < stic->req_bus->intak)
    {
        /* ---------------------------------------------------------------- */
        /*  Yes:  Drop the write.                                           */
        /* ---------------------------------------------------------------- */
//jzp_printf("access_time = %llu  accessible = %llu  intak = %llu\n", access_time, stic->stic_accessible, stic->req_bus->intak);
        return;
    }
#endif

    /* -------------------------------------------------------------------- */
    /*  If writing location 0x20, enable the display.                       */
    /* -------------------------------------------------------------------- */
    if (addr == 0x0020)
    {
//jzp_printf("got ve post, stic->phase = %d\n", stic->phase);
        stic->ve_post = 1;
    }

    /* -------------------------------------------------------------------- */
    /*  If writing location 0x21, put the display into FGBG mode.           */
    /* -------------------------------------------------------------------- */
    if (addr == 0x0021)
    {
        if (stic->mode != 1) stic->bt_dirty = 1;
        stic->mode = 1;
//jzp_printf("FOREGROUND/BACKGROUND\n");
        stic->upd  = stic_draw_fgbg;
    }

    /* -------------------------------------------------------------------- */
    /*  Now capture the write and store it in its raw, encoded form (after  */
    /*  adjusting for the and/or masks).  If the old != new, mark the frame */
    /*  as 'dirty'.                                                         */
    /* -------------------------------------------------------------------- */
    old  = stic->raw[addr];
    data &= stic_reg_mask[addr].and_mask;
    data |= stic_reg_mask[addr].or_mask;
    stic->raw[addr] = data;

    if (old != data)
    {
        stic->bt_dirty = 1;
        if (addr == 0x2C)
        {
            gfx_set_bord(stic->gfx, data & 0xF);
        }
    }
}

/* ======================================================================== */
/*  STIC_RESET   -- Reset state internal to the STIC                        */
/* ======================================================================== */
void stic_reset(periph_t *per)
{
    stic_t *stic = (stic_t*)per->parent;
    int a;

    /* -------------------------------------------------------------------- */
    /*  Fill all the STIC registers with 1.                                 */
    /* -------------------------------------------------------------------- */
    memset(stic->raw, 0, sizeof(stic->raw));  /* first, zero it */
    /* then fill it with 1s via ctrl writes */
    for (a = 0x00; a < 0x40; a++)
    {
        if (a != 0x20)
            stic_ctrl_wr(per, per->parent, a, 0xFFFF);
    }

    /* -------------------------------------------------------------------- */
    /*  Resync the internal state machine.                                  */
    /* -------------------------------------------------------------------- */
    stic->fifo_ptr        = 0;
    stic->stic_accessible = 0;
    stic->gmem_accessible = 0;
    stic->req_bus->intak  = ~0ULL;
    stic->req_bus->next_busrq = ~0ULL;
    stic->req_bus->next_intrq = ~0ULL;
    /*
    stic->stic_cr.min_tick = 1;
    stic->stic_cr.max_tick = phase_len;
    stic->next_phase      += phase_len;
    stic->phase            = new_phase;
    */
}


/* ======================================================================== */
/*  STIC_BTAB_WR -- Capture writes to the background cards.                 */
/* ======================================================================== */
void stic_btab_wr(periph_t *per, periph_t *req, uint_32 addr, uint_32 data)
{
    stic_t *stic = (stic_t*)per->parent;

    /* -------------------------------------------------------------------- */
    /*  Note -- this architecture has problems if I want to accurately      */
    /*  model the incremental sampling of BACKTAB that the STIC performs    */
    /*  throughout display time.  To do it correctly w/ this setup, I need  */
    /*  to double-buffer here, which shouldn't be too bad.  What's 240      */
    /*  words, really?                                                      */
    /* -------------------------------------------------------------------- */
    (void)req;  /* this will become un-ignored later. */

    data &= 0x3FFF;  /* only lower 14 bits seen by STIC. */
    
    if (addr < 0xF0)
    {
        if (data != stic->btab_sr[addr])
            stic->bt_dirty = 1;

        stic->btab_sr[addr] = data;
    }
}


/* ======================================================================== */
/*  STIC_GMEM_WR -- Capture writes to the Graphics RAM.                     */
/* ======================================================================== */
void stic_gmem_wr(periph_t *per, periph_t *req, uint_32 addr, uint_32 data)
{
    stic_t *stic = (stic_t*)per->parent;
    uint_64 access_time = req && req->req ? req->req->now + 4 : 0;

#if 1
    /* -------------------------------------------------------------------- */
    /*  Drop the write if in Bus Isolation mode.                            */
    /* -------------------------------------------------------------------- */
    if (access_time > stic->gmem_accessible || 
        access_time < stic->req_bus->intak)
    {
        return;
    }
#endif

    /* -------------------------------------------------------------------- */
    /*  We're mapped into the entire 4K address space for GRAM/GROM.  Drop  */
    /*  all writes for GROM addresses.                                      */
    /* -------------------------------------------------------------------- */
    if ((addr & 0x0FFF) < 0x0800)
        return;

    /* -------------------------------------------------------------------- */
    /*  Mask according to what the GRAM will actually see address and data  */
    /*  wise.  As a result, this should even correctly work for the many    */
    /*  GRAM write-aliases.                                                 */
    /* -------------------------------------------------------------------- */
    addr  = (addr & 0x01FF) + 0x0800;
    data &= 0x00FF;  /* Only the lower 8 bits of a GRAM write matter. */

    if (data != stic->gmem[addr]) 
        stic->gr_dirty = 1;

    stic->gmem[addr] = data;
}


/* ======================================================================== */
/*  STIC_GMEM_POKE -- Same as GMEM_WR, except ignores bus isolation.        */
/* ======================================================================== */
void stic_gmem_poke(periph_t *per, periph_t *req, uint_32 addr, uint_32 data)
{
    stic_t *stic = (stic_t*)per->parent;

    (void)req;

    /* -------------------------------------------------------------------- */
    /*  Don't allow pokes to GROM.                                          */
    /* -------------------------------------------------------------------- */
    if ((addr & 0x0FFF) < 0x0800)
        return;

    /* -------------------------------------------------------------------- */
    /*  Mask according to what the GRAM will actually see address and data  */
    /*  wise.  As a result, this should even correctly work for the many    */
    /*  GRAM write-aliases.                                                 */
    /* -------------------------------------------------------------------- */
    addr  = (addr & 0x01FF) + 0x0800;
    data &= 0x00FF;  /* Only the lower 8 bits of a GRAM write matter. */

    if (data != stic->gmem[addr]) 
        stic->gr_dirty = 1;

    stic->gmem[addr] = data;
}


/* ======================================================================== */
/*  STIC_GMEM_RD -- Read values out of GRAM, GROM, taking into account      */
/*                  when GRAM/GROM are visible.                             */
/* ======================================================================== */
uint_32 stic_gmem_rd(periph_t *per, periph_t *req, uint_32 addr, uint_32 data)
{
    stic_t *stic = (stic_t*)per->parent;
    uint_64 access_time = req && req->req ? req->req->now + 4 : 0;

    (void)data;
    
#if 1
    /* -------------------------------------------------------------------- */
    /*  Disallow access to graphics memory if in Bus Isolation.  System     */
    /*  Memory will return $FFFF for these reads.                           */
    /* -------------------------------------------------------------------- */
    if (access_time > stic->gmem_accessible ||
        access_time < stic->req_bus->intak)
    {
//jzp_printf("access_time = %llu  gmem_accessible = %llu  intak = %llu\n", access_time, stic->gmem_accessible, stic->req_bus->intak);
        return 0x3FFF & addr;
    }
#endif

    /* -------------------------------------------------------------------- */
    /*  If this is a GRAM address, adjust it for aliases.                   */
    /* -------------------------------------------------------------------- */
    if (addr & 0x0800)
        addr = (addr & 0x09FF);


    /* -------------------------------------------------------------------- */
    /*  Return the data.                                                    */
    /* -------------------------------------------------------------------- */
    return stic->gmem[addr] & 0xFF;
}

/* ======================================================================== */
/*  STIC_GMEM_PEEK -- Like gmem_rd, except always works.                    */
/* ======================================================================== */
uint_32 stic_gmem_peek(periph_t *per, periph_t *req, uint_32 addr, uint_32 data)
{
    stic_t *stic = (stic_t*)per->parent;
    (void)req;
    (void)data;

    /* -------------------------------------------------------------------- */
    /*  If this is a GRAM address, adjust it for aliases.                   */
    /* -------------------------------------------------------------------- */
    if (addr & 0x0800)
        addr = (addr & 0x09FF);

    /* -------------------------------------------------------------------- */
    /*  Return the data.                                                    */
    /* -------------------------------------------------------------------- */
    return stic->gmem[addr] & 0xFF;
}



/* ======================================================================== */
/*  STIC_INIT    -- Initialize this ugly ass peripheral.  Booyah!           */
/* ======================================================================== */
int stic_init
(
    stic_t      *RESTRICT stic,
    uint_16     *RESTRICT grom_img,
    req_bus_t   *RESTRICT req_bus,   
    gfx_t       *RESTRICT gfx,
    demo_t      *RESTRICT demo
)
{
    int i, j;

    /* -------------------------------------------------------------------- */
    /*  First, zero out the STIC structure to get rid of anything that      */
    /*  might be dangling.                                                  */
    /* -------------------------------------------------------------------- */
    memset((void*)stic, 0, sizeof(stic_t));

    /* -------------------------------------------------------------------- */
    /*  Set our graphics subsystem pointers.                                */
    /* -------------------------------------------------------------------- */
    stic->gfx  = gfx;
    stic->disp = gfx->vid;

    /* -------------------------------------------------------------------- */
    /*  Register the demo recorder, if there is one.                        */
    /* -------------------------------------------------------------------- */
    stic->demo = demo;

    /* -------------------------------------------------------------------- */
    /*  Initialize the bit/nibble expansion tables.                         */
    /* -------------------------------------------------------------------- */

    /*  Calculate bit-to-nibble masks b2n, b2n_r */
    for (i = 0; i < 256; i++)
    {
        uint_32 b2n, b2n_r;
        b2n = b2n_r = 0;

        for (j = 0; j < 8; j++)
            if ((i >> j) & 1)
            {
                b2n   |= 0xF << (j * 4);
                b2n_r |= 0xF << (28 - j*4);
            }

        stic_b2n  [i] = b2n;
        stic_b2n_r[i] = b2n_r;
    }

    /* Calculate bit-to-byte masks b2n_d, b2n_rd */
    for (i = 0; i < 16; i++)
    {
        uint_32 b2n_d, b2n_rd;
        b2n_d = b2n_rd = 0;

        for (j = 0; j < 4; j++)
            if ((i >> j) & 1)
            {
                b2n_d  |= 0xFF << (j * 8);
                b2n_rd |= 0xFF << (24 - j*8);
            }

        stic_b2n_d [i] = b2n_d;
        stic_b2n_rd[i] = b2n_rd;
    }

    /* Calculate n2b */
#ifdef BYTE_LE
    for (i = 0; i < 16; i++)
        for (j = 0; j < 16; j++)
            stic_n2b[16*j + i] = 256*i + j;
#else
    for (i = 0; i < 16; i++)
        for (j = 0; j < 16; j++)
            stic_n2b[16*j + i] = i + 256*j;
#endif

    /* Calculate bit_r 8-bit bit-reverse table */
    for (i = 0; i < 256; i++)
    {
        uint_32 bit_r = i;

        bit_r = ((bit_r & 0xAA) >> 1) | ((bit_r & 0x55) << 1);
        bit_r = ((bit_r & 0xCC) >> 2) | ((bit_r & 0x33) << 2);
        bit_r = ((bit_r & 0xF0) >> 4) | ((bit_r & 0x0F) << 4);

        stic_bit  [i] = i     << 8;
        stic_bit_r[i] = bit_r << 8;
    }

    /* Calculate bit-doubling tables bit_d, bit_rd */
    for (i = 0; i < 256; i++)
    {
        uint_32 bit_d = i, bit_rd = stic_bit_r[i] >> 8;

        for (j = 7; j > 0; j--)
        {
            bit_d  += bit_d  & (~0U << j);
            bit_rd += bit_rd & (~0U << j);
        }

        stic_bit_d [i] = 3 * bit_d;
        stic_bit_rd[i] = 3 * bit_rd;
    }

    /* -------------------------------------------------------------------- */
    /*  Initialize graphics memory.                                         */
    /* -------------------------------------------------------------------- */
    for (i = 0; i < 2048; i++)
        stic->gmem[i] = grom_img[i];

    /* -------------------------------------------------------------------- */
    /*  Set up our internal flags.                                          */
    /* -------------------------------------------------------------------- */
    stic->phase = 0;
    stic->mode  = 0;
    stic->upd   = stic_draw_cstk;
    
    /* -------------------------------------------------------------------- */
    /*  Record our INTRQ/BUSRQ request bus pointer.  Usually points us to   */
    /*  cp1600->req_bus.                                                    */
    /* -------------------------------------------------------------------- */
    stic->req_bus = req_bus;

    /* -------------------------------------------------------------------- */
    /*  Now, set up our peripheral functions for the main STIC peripheral.  */
    /* -------------------------------------------------------------------- */
    stic->stic_cr.read      = stic_ctrl_rd;
    stic->stic_cr.write     = stic_ctrl_wr;
    stic->stic_cr.peek      = stic_ctrl_peek;
    stic->stic_cr.poke      = stic_ctrl_wr;
    stic->stic_cr.tick      = stic_tick;
    stic->stic_cr.reset     = stic_reset;
    stic->stic_cr.min_tick  = 57; /* to get started.  stic_tick will reset. */
    stic->stic_cr.max_tick  = 57;
    stic->stic_cr.addr_base = 0x00000000;
    stic->stic_cr.addr_mask = 0x0000FFFF;
    stic->stic_cr.parent    = (void*) stic;
    stic->phase             = 0;
    stic->next_phase        = 57;
    
    /* -------------------------------------------------------------------- */
    /*  Lastly, set up the 'snooping' STIC peripherals.                     */
    /* -------------------------------------------------------------------- */
    stic->snoop_btab.read       = NULL;
    stic->snoop_btab.write      = stic_btab_wr;
    stic->snoop_btab.peek       = NULL;
    stic->snoop_btab.poke       = stic_btab_wr;
    stic->snoop_btab.tick       = NULL;
    stic->snoop_btab.min_tick   = ~0U;
    stic->snoop_btab.max_tick   = ~0U;
    stic->snoop_btab.addr_base  = 0x00000200;
    stic->snoop_btab.addr_mask  = 0x000000FF;
    stic->snoop_btab.parent     = (void*) stic;

    stic->snoop_gram.read       = stic_gmem_rd;
    stic->snoop_gram.write      = stic_gmem_wr;
    stic->snoop_gram.peek       = stic_gmem_peek;
    stic->snoop_gram.poke       = stic_gmem_poke;
    stic->snoop_gram.tick       = NULL;
    stic->snoop_gram.min_tick   = ~0U;
    stic->snoop_gram.max_tick   = ~0U;
    stic->snoop_gram.addr_base  = 0x00003000;
    stic->snoop_gram.addr_mask  = 0x0000FFFF;
    stic->snoop_gram.parent     = (void*) stic;

    return 0;
}


/* ======================================================================== */
/*  STIC BACKTAB display list architecture:                                 */
/*                                                                          */
/*  There are two main BACKTAB renderers for the STIC:  DRAW_CSTK and       */
/*  DRAW_FGBG.  These correspond to the two primary STIC modes.  Each of    */
/*  these renderers produce a pair of display lists that feed into the      */
/*  rest of the STIC display computation.                                   */
/*                                                                          */
/*  The two lists correspond to the colors of the displayed pixels for      */
/*  each card, and the bitmap of "foreground vs. background" pixels for     */
/*  each card.  What's important to note about these lists is that they     */
/*  are in card order, and are not rasterized onto the 160x96 background    */
/*  yet.                                                                    */
/*                                                                          */
/*  The display color list stores a list of 32-bit ints, each containing    */
/*  8 4-bit pixels packed as nibbles within each word.  By packing pixels   */
/*  in this manner, pixel color computation becomes exceedingly efficient.  */
/*  Indeed, it's just a couple ANDs and an OR to merge foreground and       */
/*  background colors for an entire 8-pixel row of a card.                  */
/*                                                                          */
/*  The foreground bitmap list stores a list of bytes, each containing      */
/*  bits indicating which pixels are foreground and which pixels are        */
/*  background.  A '1' bit in this bitmap indicates a foreground pixel.     */
/*  This secondary bitmap will be used to compute MOB collisions later,     */
/*  using nice simple bitwise ANDs to detect coincidence.                   */
/*                                                                          */
/*  The two lists are stored as lists of cards to limit the amount of       */
/*  addressing work that the display computation loops must do.  By         */
/*  tightly limiting the focus of these loops and by constructing a nice    */
/*  linear output pattern, this code should be fairly efficient.            */
/*                                                                          */
/*  In addition to the two display lists, the BACKTAB renderers produce     */
/*  a third, short list containing the "last background color" associated   */
/*  with each row of cards.  This information will be used to render the    */
/*  pixels to the left and above the BACKTAB image later in the engine.     */
/*                                                                          */
/*  These lists will feed into a unified render engine which will merge     */
/*  the MOB images and the BACKTAB image into the final frame buffer.       */
/* ======================================================================== */


/* ======================================================================== */
/*  STIC_DO_MOB -- Render a given MOB.                                      */
/* ======================================================================== */
LOCAL void stic_do_mob(stic_t *stic, int mob)
{
    int y, yy, y_flip, gr_idx, y_res = 8;
    uint_32 x_reg, y_reg, a_reg;
    uint_32 fg_clr, fg_msk;
    uint_16 *      RESTRICT bit_remap;
    uint_32 *const RESTRICT mob_img = stic->mob_img;
    uint_16 *const RESTRICT mob_bmp = stic->mob_bmp[mob];

    /* -------------------------------------------------------------------- */
    /*  Grab the MOB's information.                                         */
    /* -------------------------------------------------------------------- */
    x_reg = stic->raw[mob + 0x00];
    y_reg = stic->raw[mob + 0x08];
    a_reg = stic->raw[mob + 0x10];


    /* -------------------------------------------------------------------- */
    /*  Decode the various control bits from the MOB's registers.           */
    /* -------------------------------------------------------------------- */
    fg_clr = ((a_reg >> 9) & 0x08) | (a_reg & 0x07);
    fg_msk = stic_color_mask[fg_clr];

    if (x_reg & 0x0400)                /* --- double width --- */
    {                                  /* x-flip */  /* normal */
        bit_remap = (y_reg & 0x0400) ? stic_bit_rd : stic_bit_d;
    } else                             /* --- single width --- */
    {                                  /* x-flip */  /* normal */
        bit_remap = (y_reg & 0x0400) ? stic_bit_r  : stic_bit;
    }

    if (y_reg & 0x80)
        y_res = 16;

    y_flip = y_reg & 0x0800 ? y_res - 1 : 0;   /* y-flip vs. normal */

    /* -------------------------------------------------------------------- */
    /*  Decode the GROM/GRAM index.  Bits 9 and 10 are ignored if the card  */
    /*  is from GRAM, or if the display is in Foreground/Background mode.   */
    /* -------------------------------------------------------------------- */
    gr_idx = a_reg & 0xFF8;
    if (stic->mode == 1 || (gr_idx & 0x800))
        gr_idx &= 0x9F8;

    if (y_res == 16)
        gr_idx &= 0xFF0;

    /* -------------------------------------------------------------------- */
    /*  Generate the MOB's bitmap from its color and GRAM/GROM image.       */
    /*  Each MOB is generated to a 16x16 bitmap, regardless of its actual   */
    /*  size.  We handle x-flip, y-flip and x-size here.  We handle y-size  */
    /*  later when compositing the MOBs into a single bitmap.               */
    /* -------------------------------------------------------------------- */
    for (y = 0; y < y_res; y++)
    {
        uint_32 row = stic->gmem[gr_idx + y];
        uint_16 bit = bit_remap[row];
        uint_32 lpix = stic_b2n[bit >> 8  ] & fg_msk;
        uint_32 rpix = stic_b2n[bit & 0xFF] & fg_msk;

        yy = y ^ y_flip;

        mob_img[2*yy + 0] = lpix;
        mob_img[2*yy + 1] = rpix;
        mob_bmp[yy]       = bit;
    }

    for (y = y_res; y < 16; y++)
    {
        mob_img[2*y + 0] = 0;
        mob_img[2*y + 1] = 0;
        mob_bmp[y]       = 0;
    }
}

/* ======================================================================== */
/*  STIC_DRAW_MOBS -- Draw all 8 MOBs onto the 256x96 bitplane.             */
/* ======================================================================== */
LOCAL void stic_draw_mobs(stic_t *stic)
{
    int i, j;
    uint_32 *const RESTRICT mpl_img = stic->mpl_img;
    uint_32 *const RESTRICT mpl_pri = stic->mpl_pri;
    uint_32 *const RESTRICT mpl_vsb = stic->mpl_vsb;
    uint_32 *const RESTRICT mob_img = stic->mob_img;

    /* -------------------------------------------------------------------- */
    /*  First, clear the MOB plane.  We only need to clear the visibility   */
    /*  and priority bits, not the color plane.  This is because we ignore  */
    /*  the contents of the color plane wherever the visibility bit is 0.   */
    /* -------------------------------------------------------------------- */
    memset(mpl_pri, 0, 192 * 224 / 8);
    memset(mpl_vsb, 0, 192 * 224 / 8);

    /* -------------------------------------------------------------------- */
    /*  Generate the bitmaps for the 8 MOBs if they're active, and put      */
    /*  together the MOB plane.                                             */
    /* -------------------------------------------------------------------- */
    for (i = 7; i >= 0; i--)
    {
        uint_32 x_reg = stic->raw[i + 0x00];
        uint_32 y_reg = stic->raw[i + 0x08];
        uint_32 x_pos =  x_reg & 0xFF;
        uint_32 y_pos = (y_reg & 0x7F) * 2;
        uint_32 prio  = (stic->raw[i + 0x10] & 0x2000) ? ~0U : 0;
        uint_32 visb  = x_reg & 0x200;
        uint_32 y_shf = (y_reg >> 8) & 3;
        int     y_stp = 1 << y_shf;
        int     y_hgt = stic_mob_hgt[(y_reg >> 7) & 7];
        uint_32 x_rad = (x_pos & 7) * 4;
        uint_32 x_lad = 32 - x_rad;
        uint_32 x_ofs = (x_pos >> 3);
        uint_32 x_ofb = (x_pos & 31);
        uint_16 *const RESTRICT mob_bmp = stic->mob_bmp[i];
        int     y, y_res;

        /* ---------------------------------------------------------------- */
        /*  Compute bounding box for MOB and tell gfx about it.  We can     */
        /*  use this information to draw debug boxes around MOBs and other  */
        /*  nice things.  Bounding box is inclusive on all four edges.      */
        /* ---------------------------------------------------------------- */
        stic->gfx->bbox[i][0] = x_pos;
        stic->gfx->bbox[i][1] = y_pos;
        stic->gfx->bbox[i][2] = x_pos + (x_reg & 0x400 ? 15 : 7);
        stic->gfx->bbox[i][3] = y_pos + y_hgt - 1;

        /* ---------------------------------------------------------------- */
        /*  Skip this MOB if it is off-screen or is both not-visible and    */
        /*  non-interacting.                                                */
        /* ---------------------------------------------------------------- */
        if (x_pos == 0 || x_pos >= 167 || (x_reg & 0x300) == 0 ||
            y_pos >= 208)
            continue;

        /* ---------------------------------------------------------------- */
        /*  Generate the bitmap information for this MOB.                   */
        /* ---------------------------------------------------------------- */
        stic_do_mob(stic, i);

        /* ---------------------------------------------------------------- */
        /*  If this MOB is visible, put it into the color display image.    */
        /* ---------------------------------------------------------------- */
        if (!visb || stic->drop_frame > 0)
            continue;

        y_res = y_reg & 0x80 ? 16 : 8;

        if (y_pos + (y_res << y_shf) > 208)
            y_res = (207 - y_pos + (1 << y_shf)) >> y_shf;

        for (y = 0; y < y_res; y++)
        {
            uint_32 l_pix, m_pix, r_pix;    /* colors for 16-pixel MOB      */
            uint_32 l_msk, m_msk, r_msk;    /* visibility masks             */
            uint_32 l_old, m_old, r_old;    /* previous colors in disp      */
            uint_32 l_new, m_new, r_new;    /* merged old and MOB           */
            uint_32 l_pri, r_pri;           /* priority bit masks.          */
            int     b_idx, v_idx;           /* index into BMP and VISB      */
            uint_32 l_bmp, r_bmp;           /* 1-bpp bitmaps of MOB         */
                                                                          
            /* ------------------------------------------------------------ */
            /*  Get the 4-bpp images of the MOB into l_pix, m_pix.          */
            /* ------------------------------------------------------------ */
            l_pix = mob_img[2*y + 0];                                    
            m_pix = mob_img[2*y + 1];                                    
            l_msk = stic_b2n[mob_bmp[y] >> 8  ];                         
            m_msk = stic_b2n[mob_bmp[y] & 0xFF];                         
                                                                          
            /* ------------------------------------------------------------ */
            /*  Shift these right according to the X position of MOB.       */
            /*  A 16-pixel wide MOB will straddle up to 3 32-bit words      */
            /*  at 4-bpp.  (x_rad == "x position right adjust")             */
            /* ------------------------------------------------------------ */
            if (x_rad)
            {
                r_pix = (m_pix << x_lad);
                m_pix = (l_pix << x_lad) | (m_pix >> x_rad);
                l_pix =                    (l_pix >> x_rad);
                r_msk = (m_msk << x_lad);
                m_msk = (l_msk << x_lad) | (m_msk >> x_rad);
                l_msk =                    (l_msk >> x_rad);
            } else
            {
                r_pix = 0;
                r_msk = 0;
            }

            /* ------------------------------------------------------------ */
            /*  Similarly, shift the 1-bpp masks right according to the X   */
            /*  position of the MOB.                                        */
            /* ------------------------------------------------------------ */
            if (x_ofb <= 16)
            {
                l_bmp = mob_bmp[y] << (16 - x_ofb);
                r_bmp = 0;
            } else if (x_ofb <= 32)
            {
                l_bmp = mob_bmp[y] >> (x_ofb - 16);
                r_bmp = mob_bmp[y] << (48 - x_ofb);
            } else
            {
                l_bmp = 0;
                r_bmp = mob_bmp[y] << (48 - x_ofb);
            }
                                                                          
            /* ------------------------------------------------------------ */
            /*  Take the computed values and merge them into the image.     */
            /*  This is where we replicate pixels to account for y-size.    */
            /* ------------------------------------------------------------ */
            b_idx = (y_pos + (y << y_shf)) * 24 + x_ofs;
            v_idx = b_idx >> 2;
            for (j = 0; j < y_stp; j++, b_idx += 24, v_idx += 6)
            {
                /* -------------------------------------------------------- */
                /*  Now, merge the colors into the MOB color plane.         */
                /* -------------------------------------------------------- */
                l_old = mpl_img[b_idx + 0];                                
                m_old = mpl_img[b_idx + 1];                                
                r_old = mpl_img[b_idx + 2];                                
                                                                           
                l_new = (l_old & ~l_msk) | (l_pix & l_msk);                
                m_new = (m_old & ~m_msk) | (m_pix & m_msk);                
                r_new = (r_old & ~r_msk) | (r_pix & r_msk);                
                                                                           
                mpl_img[b_idx + 0] = l_new;                                
                mpl_img[b_idx + 1] = m_new;                                
                mpl_img[b_idx + 2] = r_new;                                
                                                                           
                /* -------------------------------------------------------- */
                /*  Next, set the MOB visibility bits and priority bits in  */
                /*  the corresponding 1-bpp bitmaps.                        */
                /* -------------------------------------------------------- */
                mpl_vsb[v_idx + 0] |= l_bmp;
                mpl_vsb[v_idx + 1] |= r_bmp;

                l_pri = (mpl_pri[v_idx + 0] & ~l_bmp) | (prio & l_bmp);
                r_pri = (mpl_pri[v_idx + 1] & ~r_bmp) | (prio & r_bmp);
                mpl_pri[v_idx + 0] = l_pri;
                mpl_pri[v_idx + 1] = r_pri;
            }
        }
    }
}

/* ======================================================================== */
/*  STIC_DRAW_CSTK -- Draw the 160x96 backtab image into a display list.    */
/* ======================================================================== */
LOCAL void stic_draw_cstk(stic_t *stic)
{
    int yy;
    int r, c;               /* current row, column into backtab.            */
    int bt, bti, btl;       /* Index into the BACKTAB.                      */
    int cs_idx;             /* Current color-stack position                 */
    int gr_idx;             /* Character index into GRAM/GROM.              */
    uint_32 card;           /* 14-bit card info from backtab.               */
    uint_32 px_bmp;         /* row of pixels from GRAM/GROM.                */
    int fg_clr;             /* foreground color for the card.               */
    int cstk[4];
    uint_32 fg_msk;         /* foreground color mask.                       */
    uint_32 bg_msk;         /* background color mask.                       */
    uint_32 px_msk;         /* expanded pixel mask.                         */
    uint_16 *const RESTRICT btab   = stic->btab;
    uint_32 *const RESTRICT bt_img = stic->xbt_img;
    uint_8  *const RESTRICT bt_bmp = stic->bt_bmp;

    /* -------------------------------------------------------------------- */
    /*  Read out the color-stack color values.                              */
    /* -------------------------------------------------------------------- */
    cstk[0] = stic_color_mask[stic->raw[0x28] & 0xF];
    cstk[1] = stic_color_mask[stic->raw[0x29] & 0xF];
    cstk[2] = stic_color_mask[stic->raw[0x2A] & 0xF];
    cstk[3] = stic_color_mask[stic->raw[0x2B] & 0xF];

    cs_idx = 0;
    bg_msk = cstk[0];
    /* -------------------------------------------------------------------- */
    /*  Step by rows and columns filling tiles.                             */
    /* -------------------------------------------------------------------- */
    for (bti = 8*24 + 1, bt = btl = r = c = 0; bt < 240; bt++, bti++, btl += 8)
    {

        /* ---------------------------------------------------------------- */
        /*  For each tile, do the following:                                */
        /*   -- If it's colored-squares, render as colored squares,         */
        /*      and move to the next tile.                                  */
        /*   -- If it's a color-stack advance, advance the color stack.     */
        /*   -- If we haven't rendered the tile yet, render it.             */
        /* ---------------------------------------------------------------- */
        card = btab[bt];                                         
                                                                          
        /* ---------------------------------------------------------------- */
        /*  Handle colored-squares cards.                                   */
        /* ---------------------------------------------------------------- */
        if ((card & 0x1800) == 0x1000)
        {
            uint_32 csq0, csq1, csq2, csq3;
            uint_32 csq_top, csq_bot;
            uint_32 bmp_top, bmp_bot;

            /* ------------------------------------------------------------ */
            /*                                                              */
            /*    13  12  11  10   9   8   7   6   5   4   3   2   1   0    */
            /*  +----+---+---+---+---+---+---+---+---+---+---+---+---+---+  */
            /*  |Pix3| 1   0 |Pix. 3 |Pix 2 color|Pix 1 color|Pix 0 color|  */
            /*  |Bit2|       |bit 0-1|   (0-7)   |   (0-7)   |   (0-7)   |  */
            /*  +----+---+---+---+---+---+---+---+---+---+---+---+---+---+  */
            /*                                                              */
            /*  The four pixels are displayed within an 8x8 card like so:   */
            /*                                                              */
            /*                       +-----+-----+                          */
            /*                       |Pixel|Pixel|                          */
            /*                       |  0  |  1  |                          */
            /*                       +-----+-----+                          */
            /*                       |Pixel|Pixel|                          */
            /*                       |  2  |  3  |                          */
            /*                       +-----+-----+                          */
            /*                                                              */
            /*  Notes:                                                      */
            /*                                                              */
            /*   -- Colors 0 through 6 display directly from the Primary    */
            /*      Color Set.                                              */
            /*                                                              */
            /*   -- Color 7 actually displays the current color on the top  */
            /*      of the color-stack.                                     */
            /*                                                              */
            /*   -- Colors 0 through 6 behave as "on" pixels that will      */
            /*      interact with MOBs.  Color 7 behaves as "off" pixels    */
            /*      and does not interact with MOBs.                        */
            /* ------------------------------------------------------------ */
            csq0 =  (card >> 0) & 7;
            csq1 =  (card >> 3) & 7;
            csq2 =  (card >> 6) & 7;
            csq3 = ((card >> 9) & 3) | ((card >> 11) & 4);

            bmp_top = bmp_bot = 0xFF;

            if (csq0 == 7) { csq0 = bg_msk & 0xF; bmp_top &= 0x0F; }
            if (csq1 == 7) { csq1 = bg_msk & 0xF; bmp_top &= 0xF0; }
            if (csq2 == 7) { csq2 = bg_msk & 0xF; bmp_bot &= 0x0F; }
            if (csq3 == 7) { csq3 = bg_msk & 0xF; bmp_bot &= 0xF0; }

            csq0 = stic_color_mask[csq0];
            csq1 = stic_color_mask[csq1];
            csq2 = stic_color_mask[csq2];
            csq3 = stic_color_mask[csq3];

            csq_top = (0xFFFF0000 & csq0) | (0x0000FFFF & csq1);
            csq_bot = (0xFFFF0000 & csq2) | (0x0000FFFF & csq3);

            bt_img[bti + 0*24] = csq_top;
            bt_img[bti + 1*24] = csq_top;
            bt_img[bti + 2*24] = csq_top;
            bt_img[bti + 3*24] = csq_top;
            bt_img[bti + 4*24] = csq_bot;
            bt_img[bti + 5*24] = csq_bot;
            bt_img[bti + 6*24] = csq_bot;
            bt_img[bti + 7*24] = csq_bot;

            bt_bmp[btl + 0] = bmp_top;
            bt_bmp[btl + 1] = bmp_top;
            bt_bmp[btl + 2] = bmp_top;
            bt_bmp[btl + 3] = bmp_top;
            bt_bmp[btl + 4] = bmp_bot;
            bt_bmp[btl + 5] = bmp_bot;
            bt_bmp[btl + 6] = bmp_bot;
            bt_bmp[btl + 7] = bmp_bot;

            /* ------------------------------------------------------------ */
            /*  Skip remainder of processing for this block since the       */
            /*  colored square mode is a special case.                      */
            /* ------------------------------------------------------------ */
            if (c++ == 19) { c=0; stic->last_bg[r++] = bg_msk; bti += 8*24-20; }
            continue;
        }                                                                 
                                                                          
        /* ---------------------------------------------------------------- */
        /*  The color stack advances when bit 13 is one.                    */
        /* ---------------------------------------------------------------- */
        if (card & 0x2000)                                                
        {                                                                 
            cs_idx = (cs_idx + 1) & 3;                                    
            bg_msk = cstk[cs_idx];
        }                                                                 
                                                                          
        /* ---------------------------------------------------------------- */
        /*  Extract the GROM/GRAM index from bits 11..3.  If the card is    */
        /*  from GRAM, ignore bits 10..9.                                   */
        /* ---------------------------------------------------------------- */
        gr_idx = card & 0xFF8;                                            
        if (gr_idx & 0x800)  /* is card from GRAM? */
            gr_idx &= 0x9F8;                                              
                                                                          
        /* ---------------------------------------------------------------- */
        /*  The foreground color comes from bits 12 and 2..0.               */
        /* ---------------------------------------------------------------- */
        fg_clr = ((card >> 9) & 0x8) | (card & 7);                        
        fg_msk = stic_color_mask[fg_clr];                                 
                                                                          
        /* ---------------------------------------------------------------- */
        /*  Now blit the bits into the packed-nibble display list.          */
        /* ---------------------------------------------------------------- */
        for (yy = 0; yy < 8; yy++)
        {
            px_bmp = stic->gmem[gr_idx + yy];
            px_msk = stic_b2n[px_bmp];

            bt_bmp[btl + yy   ] = px_bmp;
            bt_img[bti + yy*24] = (fg_msk & px_msk) | (bg_msk & ~px_msk);
        }

        /* ---------------------------------------------------------------- */
        /*  Advance row, column counters.                                   */
        /* ---------------------------------------------------------------- */
        if (c++ == 19) { c = 0; stic->last_bg[r++] = bg_msk; bti += 8*24-20; }
    }
}

/* ======================================================================== */
/*  STIC_DRAW_FGBG -- Draw the 160x96 backtab image into a display list.    */
/* ======================================================================== */
LOCAL void stic_draw_fgbg(stic_t *stic)
{
    int yy;
    int r, c;               /* current row, column into backtab.            */
    int bt, btl, bti;       /* Index into the BACKTAB.                      */
    int gr_idx;             /* Character index into GRAM/GROM.              */
    uint_32 card;           /* 14-bit card info from backtab.               */
    uint_32 px_bmp;         /* row of pixels from GRAM/GROM.                */
    int fg_clr;             /* foreground color for the card.               */
    int bg_clr;             /* background color for the card.               */
    uint_32 fg_msk;         /* foreground color mask.                       */
    uint_32 bg_msk;         /* background color mask.                       */
    uint_32 px_msk;         /* expanded pixel mask.                         */
    uint_16 *const RESTRICT btab   = stic->btab;
    uint_32 *const RESTRICT bt_img = stic->xbt_img;
    uint_8  *const RESTRICT bt_bmp = stic->bt_bmp;

    /* -------------------------------------------------------------------- */
    /*  Step by rows and columns filling tiles.                             */
    /* -------------------------------------------------------------------- */
    for (bti = 8*24 + 1, bt = btl = r = c = 0; bt < 240; bt++, bti++, btl += 8)
    {
        /* ---------------------------------------------------------------- */
        /*  For each tile, do the following:                                */
        /*   -- Extract foreground, background and card number.             */
        /*   -- Draw it.                                                    */
        /* ---------------------------------------------------------------- */
        card = btab[bt];

        /* ---------------------------------------------------------------- */
        /*  The GRAM/GROM index comes from bit 11 and bits 8..3.            */
        /* ---------------------------------------------------------------- */
        gr_idx = card & 0x9F8;                                            
                                                                          
        /* ---------------------------------------------------------------- */
        /*  The foreground color comes from bits 2..0.                      */
        /*  The background color comes from bit 12, 13, 10 and 9, in that   */
        /*  annoying order.  At least bits 3, 1, and 0 are right.           */
        /* ---------------------------------------------------------------- */
        fg_clr = (card & 7);                                              
        bg_clr = ((card >> 9) & 0xB) | ((card >> 11) & 0x4);
                                                                          
        /* ---------------------------------------------------------------- */
        /*  Convert colors to color masks.                                  */
        /* ---------------------------------------------------------------- */
        fg_msk = stic_color_mask[fg_clr];
        bg_msk = stic_color_mask[bg_clr];
                                                                          
        /* ---------------------------------------------------------------- */
        /*  Now blit the bits into the packed-nibble display list.          */
        /* ---------------------------------------------------------------- */
        for (yy = 0; yy < 8; yy++)
        {
            px_bmp = stic->gmem[gr_idx + yy];
            px_msk = stic_b2n[px_bmp];

            bt_bmp[btl + yy   ] = px_bmp;
            bt_img[bti + yy*24] = (fg_msk & px_msk) | (bg_msk & ~px_msk);
        }

        /* ---------------------------------------------------------------- */
        /*  Advance row, column counters.                                   */
        /* ---------------------------------------------------------------- */
        if (c++ == 19) { c = 0; stic->last_bg[r++] = bg_msk; bti += 8*24-20; }
    }
}

/* ======================================================================== */
/*  STIC_FIX_BORD -- Trim the display list and MOB image to 159 columns.    */
/* ======================================================================== */
LOCAL void stic_fix_bord(stic_t *stic)
{
    int i, j;
    uint_8  *const RESTRICT bt_bmp = stic->bt_bmp;
    uint_32 *const RESTRICT mpl_vsb = stic->mpl_vsb;
    uint_32 bord = stic->raw[0x2C] & 0xF;
    uint_32 n_msk, b_msk;
    int h_dly = stic->raw[0x30] & 7;
 
    /* -------------------------------------------------------------------- */
    /*  Make sure column 159 holds nothing interesting, and has no bg bits  */
    /*  beyond column 160 to cause false collisions.                        */
    /* -------------------------------------------------------------------- */
    n_msk = 0xFFFFFFF0 << (h_dly * 4);
    b_msk = 0xFFFFFFFF << (h_dly);
    bord  = stic_color_mask[bord] & ~n_msk;

    for (i = 0; i < 12; i++)
    {
        for (j = 0; j < 8; j++)
        {
            uint_8  new_bmp =  bt_bmp[19*8 + 20*8*i + j] & b_msk;
            bt_bmp[19*8 + 20*8*i + j] = new_bmp;
        }
    }

    /* -------------------------------------------------------------------- */
    /*  We do the same for the MOBs, but we do this to column 167, because  */
    /*  the MOB bitmap starts 8 pixels to the left of the backtab bitmap.   */
    /* -------------------------------------------------------------------- */
    b_msk = 0xFE000000 << (h_dly);
    for (i = 0; i < 224; i++)
    {
        uint_32 new_vsb =  mpl_vsb[i*6  +  5] & b_msk;
        mpl_vsb[i*6  +  5] = new_vsb;
    }

    /* -------------------------------------------------------------------- */
    /*  Trim the MOB collision bitmaps for left, right edges.               */
    /* -------------------------------------------------------------------- */
    for (i = 0; i < 8; i++)
    {
        uint_32 le_msk, re_msk, msk;
        uint_32 x = (stic->raw[i + 0x00] & 0xFF) + h_dly;

        le_msk = x < 8   ? 0xFFFFFE00 <<  x        : 0;
        re_msk = x > 150 ? 0x00007FFF >> (167 - x) : 0;
        msk    = ~(le_msk | re_msk);

        for (j = 0; j < 16; j++)
            stic->mob_bmp[i][j] &= msk;
    }
}

/* ======================================================================== */
/*  STIC_MERGE_PLANES -- Merge MOB and BACKTAB planes.                      */
/* ======================================================================== */
LOCAL void stic_merge_planes(stic_t *stic)
{
    uint_32 *const RESTRICT image   = stic->image;
    //uint_32 *const RESTRICT bt_img  = stic->bt_img;
    uint_8  *const RESTRICT bt_bmp  = stic->bt_bmp;
    uint_32 *const RESTRICT xbt_img = stic->xbt_img;
    uint_32 *const RESTRICT xbt_bmp = stic->xbt_bmp;
    uint_32 *const RESTRICT mpl_img = stic->mpl_img;
    uint_32 *const RESTRICT mpl_vsb = stic->mpl_vsb;
    uint_32 *const RESTRICT mpl_pri = stic->mpl_pri;
    int bt, ri, bti_idx, btb_idx, img_idx, bmp_idx, r, c, y, cc;
    int img_ofs;
    int v_dly, h_dly, top, lft;
    uint_32 bord = stic_color_mask[stic->raw[0x2C] & 0xF];

#if 0
    /* -------------------------------------------------------------------- */
    /*  First, re-tile the backtab display list.  That should be quick.     */
    /* -------------------------------------------------------------------- */
    for (bt = r = 0, ri = 1 + 8*24; r < 12; r++, ri += 8*24)
    {
        for (c = 0; c < 20; c++, bt++)
        {
            uint_32 img0 = bt_img[bt*8 + 0];
            uint_32 img1 = bt_img[bt*8 + 1];
            uint_32 img2 = bt_img[bt*8 + 2];
            uint_32 img3 = bt_img[bt*8 + 3];
            uint_32 img4 = bt_img[bt*8 + 4];
            uint_32 img5 = bt_img[bt*8 + 5];
            uint_32 img6 = bt_img[bt*8 + 6];
            uint_32 img7 = bt_img[bt*8 + 7];

            xbt_img[ri + c + 0*24] = img0;
            xbt_img[ri + c + 1*24] = img1;
            xbt_img[ri + c + 2*24] = img2;
            xbt_img[ri + c + 3*24] = img3;
            xbt_img[ri + c + 4*24] = img4;
            xbt_img[ri + c + 5*24] = img5;
            xbt_img[ri + c + 6*24] = img6;
            xbt_img[ri + c + 7*24] = img7;
        }
    }
#endif

    /* -------------------------------------------------------------------- */
    /*  Retile the fg/bg bitmap too.                                        */
    /* -------------------------------------------------------------------- */
    for (bt = r = 0, ri = 8*6; r < 12; r++, ri += 8*6, bt += 20)
    {
        for (y = 0; y < 8; y++)
        {
            uint_32 bmp0 = (bt_bmp[bt*8 +  0*8 + y] << 16) |
                           (bt_bmp[bt*8 +  1*8 + y] <<  8) |
                           (bt_bmp[bt*8 +  2*8 + y]      );
            uint_32 bmp1 = (bt_bmp[bt*8 +  3*8 + y] << 24) |
                           (bt_bmp[bt*8 +  4*8 + y] << 16) |
                           (bt_bmp[bt*8 +  5*8 + y] <<  8) |
                           (bt_bmp[bt*8 +  6*8 + y]      );
            uint_32 bmp2 = (bt_bmp[bt*8 +  7*8 + y] << 24) |
                           (bt_bmp[bt*8 +  8*8 + y] << 16) |
                           (bt_bmp[bt*8 +  9*8 + y] <<  8) |
                           (bt_bmp[bt*8 + 10*8 + y]      );
            uint_32 bmp3 = (bt_bmp[bt*8 + 11*8 + y] << 24) |
                           (bt_bmp[bt*8 + 12*8 + y] << 16) |
                           (bt_bmp[bt*8 + 13*8 + y] <<  8) |
                           (bt_bmp[bt*8 + 14*8 + y]      );
            uint_32 bmp4 = (bt_bmp[bt*8 + 15*8 + y] << 24) |
                           (bt_bmp[bt*8 + 16*8 + y] << 16) |
                           (bt_bmp[bt*8 + 17*8 + y] <<  8) |
                           (bt_bmp[bt*8 + 18*8 + y]      );
            uint_32 bmp5 = (bt_bmp[bt*8 + 19*8 + y] << 24);

            xbt_bmp[ri + 0 + y*6] = bmp0;
            xbt_bmp[ri + 1 + y*6] = bmp1;
            xbt_bmp[ri + 2 + y*6] = bmp2;
            xbt_bmp[ri + 3 + y*6] = bmp3;
            xbt_bmp[ri + 4 + y*6] = bmp4;
            xbt_bmp[ri + 5 + y*6] = bmp5;
        }
    }

    /* -------------------------------------------------------------------- */
    /*  Stop here if we're dropping the frame.                              */
    /* -------------------------------------------------------------------- */
    if (stic->drop_frame)
        return;

    /* -------------------------------------------------------------------- */
    /*  fill in colors based on "last_bg" along the top and left.           */
    /* -------------------------------------------------------------------- */
    h_dly   = (stic->raw[0x30] & 7);
    v_dly   = (stic->raw[0x31] & 7);
    img_ofs = v_dly*2 * 24;

    memset(xbt_img, stic->last_bg[11], (8*192 + 8)/2);
    {
        uint_32 h_msk = 0xfffffff0 << (h_dly * 4);
        uint_32 h_fix = (stic->last_bg[11] & h_msk) | (bord & ~h_msk);

        for (y = 0, ri = 20; y < 8; y++, ri += 24)
            xbt_img[ri] = h_fix;
    }

    for (r = 0, ri = 9*24; r < 12; r++)
    {
        uint_32 bg_clr = stic->last_bg[r];

        for (y = 0; y < 8; y++, ri += 24)
            xbt_img[ri] = bg_clr;
    }

    /* -------------------------------------------------------------------- */
    /*  now channel between the mob and backtab images into the final       */
    /*  display image.  we also account for h_dly/v_dly here.  the vert     */
    /*  delay is really cheap -- we just draw further down the screen.      */
    /*  the horz delay isn't quite so cheap but is still not terribly       */
    /*  expensive.  we shift the pixels right as a huge extended-precision  */
    /*  right shift.                                                        */
    /* -------------------------------------------------------------------- */

    if (h_dly == 0)
    {
        int len = 208 - v_dly*2;

        for (r = img_idx = bmp_idx = bti_idx = btb_idx = 0; 
             r < len; r++, img_idx += 24, bmp_idx += 6)
        {
            for (c = cc = 0; c < 24; c += 4, cc++)
            {
                uint_32 btab_0 = xbt_img[bti_idx + c + 0];
                uint_32 btab_1 = xbt_img[bti_idx + c + 1];
                uint_32 btab_2 = xbt_img[bti_idx + c + 2];
                uint_32 btab_3 = xbt_img[bti_idx + c + 3];

                uint_32 mobs_0 = mpl_img[img_idx + c + 0];
                uint_32 mobs_1 = mpl_img[img_idx + c + 1];
                uint_32 mobs_2 = mpl_img[img_idx + c + 2];
                uint_32 mobs_3 = mpl_img[img_idx + c + 3];

                uint_32 bt_msk = xbt_bmp[btb_idx + cc];
                uint_32 vs_msk = mpl_vsb[bmp_idx + cc];
                uint_32 pr_msk = mpl_pri[bmp_idx + cc];
                uint_32 mb_msk = vs_msk & ~(pr_msk & bt_msk);

                uint_32 mask_0 = stic_b2n[(mb_msk >> 24)       ];
                uint_32 mask_1 = stic_b2n[(mb_msk >> 16) & 0xFF];
                uint_32 mask_2 = stic_b2n[(mb_msk >>  8) & 0xFF];
                uint_32 mask_3 = stic_b2n[(mb_msk      ) & 0xFF];

                uint_32 img_0  = (mobs_0 & mask_0) | (btab_0 & ~mask_0);
                uint_32 img_1  = (mobs_1 & mask_1) | (btab_1 & ~mask_1);
                uint_32 img_2  = (mobs_2 & mask_2) | (btab_2 & ~mask_2);
                uint_32 img_3  = (mobs_3 & mask_3) | (btab_3 & ~mask_3);

                image[img_idx + c + 0 + img_ofs] = img_0;
                image[img_idx + c + 1 + img_ofs] = img_1;
                image[img_idx + c + 2 + img_ofs] = img_2;
                image[img_idx + c + 3 + img_ofs] = img_3;
            }

            if (r & 1) { bti_idx += 24; btb_idx += 6; }
            else       { xbt_img[bti_idx] = xbt_img[bti_idx + 24]; }
        }
    } else
    {
        int r_shf = h_dly * 4;
        int l_shf = 32 - r_shf;
        int len   = 208 - v_dly*2;

        for (r = img_idx = bmp_idx = bti_idx = btb_idx = 0; 
             r < len; r++, img_idx += 24, bmp_idx += 6)
        {
            uint_32 pimg_3 = 0;  /* extending on left w/ 0 is ok */

#if 1
            for (c = cc = 0; c < 24; c += 4, cc++)
            {
                uint_32 btab_0 = xbt_img[bti_idx + c + 0];
                uint_32 btab_1 = xbt_img[bti_idx + c + 1];
                uint_32 btab_2 = xbt_img[bti_idx + c + 2];
                uint_32 btab_3 = xbt_img[bti_idx + c + 3];

                uint_32 mobs_0 = mpl_img[img_idx + c + 0];
                uint_32 mobs_1 = mpl_img[img_idx + c + 1];
                uint_32 mobs_2 = mpl_img[img_idx + c + 2];
                uint_32 mobs_3 = mpl_img[img_idx + c + 3];

                uint_32 bt_msk = xbt_bmp[btb_idx + cc];
                uint_32 vs_msk = mpl_vsb[bmp_idx + cc];
                uint_32 pr_msk = mpl_pri[bmp_idx + cc];
                uint_32 mb_msk = vs_msk & ~(pr_msk & bt_msk);

                uint_32 mask_0 = stic_b2n[(mb_msk >> 24)       ];
                uint_32 mask_1 = stic_b2n[(mb_msk >> 16) & 0xFF];
                uint_32 mask_2 = stic_b2n[(mb_msk >>  8) & 0xFF];
                uint_32 mask_3 = stic_b2n[(mb_msk      ) & 0xFF];

                uint_32 ximg_0 = (mobs_0 & mask_0) | (btab_0 & ~mask_0);
                uint_32 ximg_1 = (mobs_1 & mask_1) | (btab_1 & ~mask_1);
                uint_32 ximg_2 = (mobs_2 & mask_2) | (btab_2 & ~mask_2);
                uint_32 ximg_3 = (mobs_3 & mask_3) | (btab_3 & ~mask_3);

                uint_32 img_0  = (pimg_3 << l_shf) | (ximg_0 >> r_shf);
                uint_32 img_1  = (ximg_0 << l_shf) | (ximg_1 >> r_shf);
                uint_32 img_2  = (ximg_1 << l_shf) | (ximg_2 >> r_shf);
                uint_32 img_3  = (ximg_2 << l_shf) | (ximg_3 >> r_shf);

                image[img_idx + c + 0 + img_ofs] = img_0;
                image[img_idx + c + 1 + img_ofs] = img_1;
                image[img_idx + c + 2 + img_ofs] = img_2;
                image[img_idx + c + 3 + img_ofs] = img_3;

                pimg_3 = ximg_3;
            }
#else
            for (c = cc = 0; c < 24; c += 4, cc++)
            {
                uint_32 bt_msk, btab_0, btab_1, btab_2, btab_3;
                uint_32 vs_msk, mobs_0, mobs_1, mobs_2, mobs_3;
                uint_32 pr_msk, mask_0, mask_1, mask_2, mask_3;
                uint_32 mb_msk, ximg_0, ximg_1, ximg_2, ximg_3;
                uint_32 img_0;
                uint_32 img_1;
                uint_32 img_2;
                uint_32 img_3;

                bt_msk = xbt_bmp[btb_idx + cc];
                vs_msk = mpl_vsb[bmp_idx + cc];
                pr_msk = mpl_pri[bmp_idx + cc];
                mb_msk = vs_msk & ~(pr_msk & bt_msk);

                btab_0 = xbt_img[bti_idx + c + 0];
                mobs_0 = mpl_img[img_idx + c + 0];
                mask_0 = stic_b2n[(mb_msk >> 24)       ];
                ximg_0 = (mobs_0 & mask_0) | (btab_0 & ~mask_0);
                img_0  = (pimg_3 << l_shf) | (ximg_0 >> r_shf);
                image[img_idx + c + 0 + img_ofs] = img_0;

                btab_1 = xbt_img[bti_idx + c + 1];
                mobs_1 = mpl_img[img_idx + c + 1];
                mask_1 = stic_b2n[(mb_msk >> 16) & 0xFF];
                ximg_1 = (mobs_1 & mask_1) | (btab_1 & ~mask_1);
                img_1  = (ximg_0 << l_shf) | (ximg_1 >> r_shf);
                image[img_idx + c + 1 + img_ofs] = img_1;

                btab_2 = xbt_img[bti_idx + c + 2];
                mobs_2 = mpl_img[img_idx + c + 2];
                mask_2 = stic_b2n[(mb_msk >>  8) & 0xFF];
                ximg_2 = (mobs_2 & mask_2) | (btab_2 & ~mask_2);
                img_2  = (ximg_1 << l_shf) | (ximg_2 >> r_shf);
                image[img_idx + c + 2 + img_ofs] = img_2;
                
                btab_3 = xbt_img[bti_idx + c + 3];
                mobs_3 = mpl_img[img_idx + c + 3];
                mask_3 = stic_b2n[(mb_msk      ) & 0xFF];
                ximg_3 = (mobs_3 & mask_3) | (btab_3 & ~mask_3);
                img_3  = (ximg_2 << l_shf) | (ximg_3 >> r_shf);
                image[img_idx + c + 3 + img_ofs] = img_3;

                pimg_3 = ximg_3;
            }
#endif

            if (r & 1) { bti_idx += 24; btb_idx += 6; }
            else       { xbt_img[bti_idx] = xbt_img[bti_idx + 24]; }
        }
    }

    /* -------------------------------------------------------------------- */
    /*  Apply top and bottom borders.                                       */
    /* -------------------------------------------------------------------- */
    top = (stic->raw[0x32] & 2 ? 32 : 16);  /* 32 or 16 rows. */
    memset(image,          bord, top * 192 / 2);
    memset(image + 208*24, bord,  16 * 192 / 2);

    /* -------------------------------------------------------------------- */
    /*  Apply left and right borders.                                       */
    /* -------------------------------------------------------------------- */
    lft = stic->raw[0x32] & 1;
    for (r = 16, ri = 16*24; r < 208; r++, ri += 24)
    {
        image[ri     ] = bord;
        image[ri + 21] = bord;
        if (lft) image[ri + 1] = bord;
    }
}

/* ======================================================================== */
/*  STIC_PUSH_VID -- Temporary:  Unpack the 4-bpp image to 160x200 8bpp     */
/* ======================================================================== */
LOCAL void stic_push_vid(stic_t *stic)
{
    int y, x;
    uint_32 *RESTRICT vid   = (uint_32*)stic->disp;
    uint_32 *RESTRICT image = stic->image;

    image += 12*24 + 1;

    for (y = 12; y < 212; y++)
    {
        for (x = 1; x <= 20; x++)
        {
#ifdef BYTE_LE
            uint_32 pix = *image++;
            uint_32 p76 = stic_n2b[pix       & 0xFF];
            uint_32 p54 = stic_n2b[pix >>  8 & 0xFF];
            uint_32 p7654 = (p76 << 16) | p54;
            uint_32 p32 = stic_n2b[pix >> 16 & 0xFF];
            uint_32 p10 = stic_n2b[pix >> 24       ];
            uint_32 p3210 = (p32 << 16) | p10;

            *vid++ = p3210;
            *vid++ = p7654;
#else
            uint_32 pix   = *image++;
            uint_32 p01   = stic_n2b[pix >> 24       ];
            uint_32 p23   = stic_n2b[pix >> 16 & 0xFF];
            uint_32 p0123 = (p01 << 16) | p23;
            uint_32 p45   = stic_n2b[pix >>  8 & 0xFF];
            uint_32 p67   = stic_n2b[pix       & 0xFF];
            uint_32 p4567 = (p45 << 16) | p67;

            *vid++ = p0123;
            *vid++ = p4567;
#endif
        }
        image += 4;
    }
}


/* ======================================================================== */
/*  STIC_MOB_COLLDET -- Do collision detection on all the MOBs.             */
/*                      XXX: h_dly and v_dly??                              */
/* ======================================================================== */
LOCAL void stic_mob_colldet(stic_t *stic)
{
    int mob0, mob1;
    int h_dly =  stic->raw[0x30] & 7;
    int v_dly = (stic->raw[0x31] & 7) * 2;

    for (mob0 = 0; mob0 < 8; mob0++)
    {
        int xl0, xh0;
        int yl0, yh0;
        int yhgt0 = stic_mob_hgt[(stic->raw[mob0 + 0x08] >> 7) & 7];
        int yshf0 = (stic->raw[mob0 + 0x08] >> 8) & 3;

        /* ---------------------------------------------------------------- */
        /*  Decode the first MOB.  Reject it trivially if off screen or     */
        /*  non-interacting.                                                */
        /* ---------------------------------------------------------------- */
        xl0 = (stic->raw[mob0 + 0x00] & 0x1FF);     /* X coord and INTR bit */
        yl0 = (stic->raw[mob0 + 0x08] & 0x07F)<<1;  /* Y coord              */

        xh0 = xl0 + (stic->raw[mob0 + 0x00] & 0x400 ? 15 : 7);
        yh0 = yl0 + yhgt0 - 1;

        if (xl0 <= 0x100 || xl0 >= (0x1A8-h_dly) || yl0 > (0xD8-v_dly))  
            continue;

        /* ---------------------------------------------------------------- */
        /*  Generate 'edge mask' which discards pixels that are outside     */
        /*  the display area.                                               */
        /* ---------------------------------------------------------------- */

        /* ---------------------------------------------------------------- */
        /*  Do MOB-to-MOB collision detection first.                        */
        /* ---------------------------------------------------------------- */
        for (mob1 = mob0 + 1; mob1 < 8; mob1++)
        {
            int xl1, xh1;
            int yl1, yh1;
            int yhgt1 = stic_mob_hgt[(stic->raw[mob1 + 0x08] >> 7) & 7];
            int yshf1 = (stic->raw[mob1 + 0x08] >> 8) & 3;
            int ylo, yhi;
            int yy0, yy1, yy;
            int ls0, ls1;

            /* ------------------------------------------------------------ */
            /*  Super trivial reject:  If we already have a collision for   */
            /*  this MOB pair, we don't need to compute again -- it'd be    */
            /*  useless since we can only set bits to 1 and it's already 1. */
            /* ------------------------------------------------------------ */
            if (((stic->raw[mob0 + 0x18] >> mob1) & 1) &&
                ((stic->raw[mob1 + 0x18] >> mob0) & 1))
                continue;

            /* ------------------------------------------------------------ */
            /*  Decode second MOB.  Do same trivial reject.                 */
            /* ------------------------------------------------------------ */
            xl1 = (stic->raw[mob1 + 0x00] & 0x1FF);     
            yl1 = (stic->raw[mob1 + 0x08] & 0x07F)<<1;  

            xh1 = xl1 + (stic->raw[mob1 + 0x00] & 0x400 ? 15 : 7);
            yh1 = yl1 + yhgt1 - 1;

            if (xl1 <= 0x100 || xl1 >= (0x1A7-h_dly) || yl1 > (0xD8-v_dly))  
                continue;

            /* ------------------------------------------------------------ */
            /*  Only slightly less trivial reject:  Bounding box compare    */
            /*  the two.  Basically, the left edge of one box must be       */
            /*  between left and right of the other.  Ditto for top edge    */
            /*  vs. top/bot for other.                                      */
            /* ------------------------------------------------------------ */
            if ((xl0 < xl1 || xl0 > xh1) && (xl1 < xl0 || xl1 > xh0))
                continue;

            if ((yl0 < yl1 || yl0 > yh1) && (yl1 < yl0 || yl1 > yh0))
                continue;

            /* ------------------------------------------------------------ */
            /*  Compute bitwise collision.                                  */
            /* ------------------------------------------------------------ */
            if (xl0 < xl1) { ls0 = xl1 - xl0; ls1 = 0; } 
            else           { ls1 = xl0 - xl1; ls0 = 0; }

            ylo = yl0 > yl1 ? yl0 : yl1;
            yhi = yh0 < yh1 ? yh0 : yh1;
            ylo = ylo < 15  - v_dly ? 15  - v_dly : 
                  ylo > 208 - v_dly ? 208 - v_dly : ylo;
            yhi = yhi < 15  - v_dly ? 15  - v_dly : 
                  yhi > 208 - v_dly ? 208 - v_dly : yhi;
            for (yy = ylo; yy <= yhi; yy++)
            {
                uint_32 mb0, mb1;

                yy0 = (yy - yl0) >> yshf0;
                yy1 = (yy - yl1) >> yshf1;

                mb0 = stic->mob_bmp[mob0][yy0] << ls0;
                mb1 = stic->mob_bmp[mob1][yy1] << ls1;

                if (mb0 & mb1) 
                    break;
            }

            if (yy <= yhi)
            {
                stic->raw[mob0 + 0x18] |= 1 << mob1;
                stic->raw[mob1 + 0x18] |= 1 << mob0;
            }
        }

        /* ---------------------------------------------------------------- */
        /*  Discard INTR bit from xl0, xh0.                                 */
        /* ---------------------------------------------------------------- */
        xl0 &= 0xFF;
        xh0 &= 0xFF;

        /* ---------------------------------------------------------------- */
        /*  Do MOB-to-BACKTAB.  Skip this test if this bit is already 1.    */
        /* ---------------------------------------------------------------- */
        if ((stic->raw[mob0 + 0x18] & 0x100) == 0x000)
        {
            uint_32 bt;
            uint_32 bt_ls, bt_rs;
            int bt_idx, yy = 0, yy0;
            int ymax = yh0 > (206 - v_dly) ? 206 - v_dly : yh0;

            bt_idx = yl0 * 3 + (xl0 >> 5);
            bt_ls  = xl0 & 31;
            bt_rs  = 32 - bt_ls;

            /* ------------------------------------------------------------ */
            /*  Sadly, we need the 'if (bt_ls)' because >>32 is undefined.  */
            /* ------------------------------------------------------------ */
            if (bt_ls)
            {
                for (yy = yl0; yy <= ymax; yy++)
                {
                    yy0 = (yy - yl0) >> yshf0;

                    bt = (stic->xbt_bmp[bt_idx    ] << bt_ls) |
                         (stic->xbt_bmp[bt_idx + 1] >> bt_rs);

                    if (bt & (stic->mob_bmp[mob0][yy0] << 16))
                        break;
                    
                    if (yy & 1) bt_idx += 6;
                }
                if (yy <= ymax)
                    stic->raw[mob0 + 0x18] |= 0x100;
            } else
            {
                for (yy = yl0; yy <= ymax; yy++)
                {
                    yy0 = (yy - yl0) >> yshf0;

                    bt = stic->xbt_bmp[bt_idx];

                    if (bt & (stic->mob_bmp[mob0][yy0] << 16))
                        break;

                    if (yy & 1) bt_idx += 6;
                }
                if (yy <= ymax)
                    stic->raw[mob0 + 0x18] |= 0x100;
            }
        }

        /* ---------------------------------------------------------------- */
        /*  Do MOB-to-Border.   Skip this test if this bit is already 1.    */
        /* ---------------------------------------------------------------- */
        if ((stic->raw[mob0 + 0x18] & 0x200) == 0x000)
        {
            uint_32 le_msk, re_msk, msk;
            int mx = xl0 + h_dly, my = yl0 + v_dly;
            int yy;
            int ymin;
            int ymax = (yh0 + v_dly) < 208 ? (yh0 + v_dly) : 208;
            int ted  =       (stic->raw[0x32] & 2) ? 32    : 16;
            uint_32 le_gen = (stic->raw[0x32] & 1) ? 0x1FF : 0x100;

            /* ------------------------------------------------------------ */
            /*  Compute left/right collisions by ANDing a bitmask with      */
            /*  each of the rows of the MOB bitmap that are inside the      */
            /*  visible field.  The "le_gen" takes into account edge ext.   */
            /* ------------------------------------------------------------ */
            le_msk = mx < 9   ? le_gen <<  mx        : 0;
            re_msk = mx > 150 ? 0x8000 >> (167 - mx) : 0;
            msk    = 0xFFFF & (le_msk | re_msk);

            /* compute top/bottom rows that l/r edges might interact with */
            ymax = (ymax - my) >> yshf0;
            ymin = my < 16 ? (15 - my) >> yshf0 : 0;
//if (le_msk) jzp_printf("le_msk = %.8X mx = %-3d\n", le_msk, mx);
//if (re_msk) jzp_printf("re_msk = %.8X mx = %-3d\n", re_msk, mx);

            /* left, right edges */
            for (yy = ymin; yy <= ymax; yy++)
            {
                if (yy < yhgt0 &&
                    stic->mob_bmp[mob0][yy] & msk)
                    break;
            }
            if (yy <= ymax)
                stic->raw[mob0 + 0x18] |= 0x200;

            /* ------------------------------------------------------------ */
            /*  Compute top/bottom collisions by examining the row(s) that  */
            /*  might intersect with either edge.  We regenerate the left/  */
            /*  right masks ignoring border extension, so that we can use   */
            /*  them to mask away the pixels that aren't included in the    */
            /*  computation.                                                */;
            /* ------------------------------------------------------------ */
            le_msk = mx < 8 ? 0xFFFFFE00 << mx : 0;      /* extend lft mask */
            re_msk = re_msk ? (re_msk << 1)- 1 : 0;      /* extend rgt mask */
            msk    = ~(le_msk | re_msk);

            /* top edge */
            if (my <= ted)
            {
//jzp_printf("ted=%-2d my=%-2d:", ted, my);
                for (yy = 15; yy < ted; yy += (1 << yshf0))
                {
                    if (my <= yy)
                    {
                        int row = (yy - my) >> yshf0;
                        if (row < yhgt0 &&
                            stic->mob_bmp[mob0][row] & msk)
                        {
//jzp_printf(" %2d,%-2d", yy, row);
                                stic->raw[mob0 + 0x18] |= 0x200;
                        }
                    }
                }
//putchar('\n');
            }

            /* bottom edge */
            if (yh0 + v_dly >= 207)
            {
                int ybot = (208 - my) >> yshf0;

                if (stic->mob_bmp[mob0][ybot] & msk)
                    stic->raw[mob0 + 0x18] |= 0x200;
            }
        }
    }
}

/* ======================================================================== */
/*  STIC_UPDATE -- wrapper around all the pieces above.                     */
/* ======================================================================== */
#ifdef BENCHMARK_STIC
LOCAL void stic_update(stic_t *stic)
{
    double a, b, c;
    static double ovhd = 1e6;

    a = get_time();
    b = get_time();
    if (b - a < ovhd) ovhd = b - a;
    a = b;

    /* draw the backtab */
    if (stic->upd) stic->upd(stic);
    c = get_time(); stic->time.draw_btab    += c - b - ovhd; b = c;

    stic_draw_mobs   (stic);
    c = get_time(); stic->time.draw_mobs    += c - b - ovhd; b = c;
    stic_fix_bord    (stic);
    c = get_time(); stic->time.fix_bord     += c - b - ovhd; b = c;
    stic_merge_planes(stic);
    c = get_time(); stic->time.merge_planes += c - b - ovhd; b = c;

    if (stic->drop_frame <= 0)
    {
        stic_push_vid    (stic);
        c = get_time(); stic->time.push_vid     += c - b - ovhd; b = c;
    } else
        stic->drop_frame--;

    stic_mob_colldet (stic);
    c = get_time(); stic->time.mob_colldet  += c - b - ovhd; b = c;

//    if (last_enable != stic->vid_enable)
    c = get_time(); stic->time.gfx_vid_enable += c - b - ovhd; 

    stic->time.full_update  += c - a - 7*ovhd;
    stic->time.total_frames++;

    if (stic->time.total_frames >= 100)
    {
        double scale = 1e6 / (double)stic->time.total_frames;

        jzp_printf("stic performance update:\n");
        jzp_printf("  draw_btab    %9.4f usec\n", stic->time.draw_btab    * scale);
        jzp_printf("  draw_mobs    %9.4f usec\n", stic->time.draw_mobs    * scale);
        jzp_printf("  fix_bord     %9.4f usec\n", stic->time.fix_bord     * scale);
        jzp_printf("  merge_planes %9.4f usec\n", stic->time.merge_planes * scale);
        jzp_printf("  push_vid     %9.4f usec\n", stic->time.push_vid     * scale);
        jzp_printf("  mob_colldet  %9.4f usec\n", stic->time.mob_colldet  * scale);
        jzp_printf("  vid_enable   %9.4f usec\n", stic->time.gfx_vid_enable*scale);
        jzp_printf("  TOTAL:       %9.4f usec\n", stic->time.full_update  * scale);

        jzp_flush();
            
        memset((void*)&stic->time, 0, sizeof(stic->time));
    }
}
#else
LOCAL void stic_update(stic_t *stic)
{
    /* draw the backtab */
    if (stic->upd) stic->upd(stic);

    stic_draw_mobs   (stic);
    stic_fix_bord    (stic);
    stic_merge_planes(stic);

    if (stic->drop_frame <= 0)
        stic_push_vid    (stic);
    else
        stic->drop_frame--;

    stic_mob_colldet (stic);

    stic->gfx->dirty = 1;
//    if (last_enable != stic->vid_enable)
}
#endif

/* ======================================================================== */
/*  STIC_TICK -- Ugh, this is where the action happens.  Whee.              */
/* ======================================================================== */
uint_32 stic_tick
(
    periph_p        per,
    uint_32         len
)
{
    stic_t *stic = (stic_t*)per->parent;
    uint_64 now = per->now, soon;
    uint_32 new_phase, phase_len;

    /* -------------------------------------------------------------------- */ 
    /*  See if we're being ticked for too little or too much time.  This    */
    /*  shouldn't happen, in theory, but it might.                          */
    /* -------------------------------------------------------------------- */ 
    if (now + len < stic->next_phase)
    {
        //jzp_printf("short tick = %d\n", len);
        stic->stic_cr.max_tick = stic->next_phase - now;
        return len;
    }
    if (now + len > stic->next_phase)
    {
        len = stic->next_phase - now;
    }

//jzp_printf("stic->phase = %d now = %d len = %d\n", stic->phase, (int)now, (int)len);
    /* -------------------------------------------------------------------- */ 
    /*  Toggle the phases and get out of here.                              */
    /*                                                                      */
    /*  PHASE  Length             Action                                    */
    /*    0    STIC_ACCESSIBLE    Start VBlank.  STIC & GMEM accessible     */
    /*                                                                      */
    /*    1    GMEM_ACCESSIBLE    STIC registers unavailable.  INTRQ goes   */
    /*         - STIC_ACCESSIBLE  away if not INTAK'd.                      */
    /*                                                                      */
    /*    2    114*v_dly + h_dly  GRAM/GROM unavailable.  Short initial     */
    /*         + 143              BUSRQ (kicks System RAM into isolation).  */
    /*                                                                      */
    /*  3..13  912                BUSRQ for row 0..11.  Copy cards for the  */
    /*                            previous row in states 4 .. 14.           */
    /*                                                                      */
    /*   14    912 - 114*v_dly    BUSRQ for row 0..11.  Copy cards for the  */
    /*         - h_dly            previous row in states 4 .. 14.           */
    /*                                                                      */
    /*   15    57.                Extra BUSRQ if v_dly == 0.   Copy cards   */
    /*                            for last row.                             */
    /*                                                                      */
    /*   16    STIC_FRAMCLKS      If video is blanked, leave everything     */  
    /*         - STIC_ACCESSIBLE  accessible.  Replaces phases 2..15.       */
    /*                                                                      */
    /*                                                                      */
    /*  This gets complicated, though.  Time delays and anything from       */
    /*  CPU->STIC has to happen with the current phase.  Anything going     */
    /*  from STIC->CPU has to happen with the next phase.                   */
    /* -------------------------------------------------------------------- */ 

    new_phase = (stic->phase + 1) & 15;
    soon = stic->next_phase;

    /* -------------------------------------------------------------------- */ 
    /*  CPU->STIC and phase timing happens with current phase.              */
    /* -------------------------------------------------------------------- */ 
    switch (stic->phase)
    {
        /* ---------------------------------------------------------------- */ 
        /*  PHASE 0:  Start of vertical blanking interval.                  */
        /* ---------------------------------------------------------------- */ 
        case 0:
        {
            if (stic->vid_enable)
                stic_update(stic);
            else
                if (stic->drop_frame > 0)
                    stic->drop_frame--;

            stic->gfx->dirty = 1;
            gfx_vid_enable(stic->gfx, stic->vid_enable);

            if (stic->req_bus)
            {
                stic->req_bus->intrq       = ASSERT_INTRQ;
                stic->req_bus->intrq_until = soon + STIC_INTRQ_HOLD;
                stic->req_bus->next_intrq  = soon + STIC_FRAMCLKS - 1;

                /* Require INTAK if previous frame enabled. */
                if (stic->vid_enable)
                    stic->req_bus->intak = ~0ULL;

//jzp_printf("asserted INTRQ(1), now = %d, soon = %d, len = %d, until = %d\n", (int)now, (int)soon, (int)len, (int)stic->req_bus->intrq_until);
            }
            stic->vid_enable       = 0;
            stic->ve_post          = 0;
            stic->bt_dirty         = 0;
            stic->gr_dirty         = 0;
            stic->ob_dirty         = 0;
            stic->stic_accessible  = soon + STIC_STIC_ACCESSIBLE;
            stic->gmem_accessible  = soon + STIC_GMEM_ACCESSIBLE;
            phase_len              = STIC_STIC_ACCESSIBLE;

            /* ------------------------------------------------------------ */ 
            /*  Update the demo recorder, if one's active.                  */
            /* ------------------------------------------------------------ */ 
            if (stic->demo)
                demo_tick(stic->demo, stic);

            break;
        }

        /* ---------------------------------------------------------------- */ 
        /*  PHASE 1:  Make STIC registers inaccessible if disp enabled.     */
        /* ---------------------------------------------------------------- */ 
        case 1:
        {
            phase_len = STIC_GMEM_ACCESSIBLE - STIC_STIC_ACCESSIBLE;

            /* ------------------------------------------------------------ */ 
            /*  Enable/disable video.                                       */
            /* ------------------------------------------------------------ */ 
            if (stic->vid_enable != (stic->ve_post!=0))
            {
//              extern int last_dis, first_dis;
                stic->bt_dirty = 1;
//              if ((stic->ve_post & 1) == 0) 
//                  jzp_printf("DIS addrs: $%.4X $%.4X\n", 
//                         0xFFFF&first_dis,  0xFFFF&last_dis);
            }
//jzp_printf("ve_post = %d\n", stic->ve_post);
            stic->vid_enable = stic->ve_post & 1;
            stic->ve_post    = stic->vid_enable << 1;

            /* ------------------------------------------------------------ */ 
            /*  If video isn't enabled, let CPU access STIC resources for   */
            /*  as long as it likes.                                        */
            /* ------------------------------------------------------------ */ 
            if (!stic->vid_enable) 
            {
                stic->stic_accessible = ~0ULL;
                stic->gmem_accessible = ~0ULL;
            } else
                stic->req_bus->next_busrq = soon + phase_len;
            break;
        }

        /* ---------------------------------------------------------------- */ 
        /*  PHASE 2:  Do the short BUSRQ.                                   */
        /* ---------------------------------------------------------------- */ 
        case 2:
        {
            int v_dly, h_dly;
            h_dly = stic->raw[0x30] & 7;
            v_dly = stic->raw[0x31] & 7;
            phase_len = 120 + 114*v_dly + h_dly;

            if (stic->vid_enable && stic->req_bus)
            {
                stic->req_bus->intrq       = ASSERT_BUSRQ;
                stic->req_bus->busrq_until = soon + STIC_BUSRQ_HOLD_FIRST;
                stic->req_bus->next_busrq  = soon + phase_len;
                stic->req_bus->busak       = 0;
//jzp_printf("asserted BUSRQ(1), now = %d, soon = %d, len = %d, until = %d\n", (int)now, (int)soon, (int)len, (int)stic->req_bus->busrq_until);
            }
            stic->fifo_ptr = 0;
            
            break;
        }

        /* ---------------------------------------------------------------- */ 
        /*  PHASE 3..13:  Do full-length BUSRQs, and copy cards from BTAB.  */
        /* ---------------------------------------------------------------- */ 
        case  3: case  4: case  5: case  6: case 7: case 8: case 9: 
        case 10: case 11: case 12: case 13:
        {
            phase_len = 912;

            if (stic->phase > 3 && 
                stic->req_bus->busak <= 
                stic->req_bus->busrq_until - STIC_BUSRQ_MARGIN)
                stic->fifo_ptr += 20;

            memcpy(stic->btab + 20*(stic->phase-3), 
                   stic->btab_sr + stic->fifo_ptr, 20*sizeof(uint_16));
            
            if (stic->vid_enable && stic->req_bus)
            {
                stic->req_bus->intrq       = ASSERT_BUSRQ;
                stic->req_bus->busrq_until = soon + STIC_BUSRQ_HOLD_NORMAL;
                stic->req_bus->next_busrq  = soon + phase_len;
                stic->req_bus->busak       = 0;
//jzp_printf("asserted BUSRQ(n), now = %d, soon = %d, len = %d, until = %d\n", (int)now, (int)soon, (int)len, (int)stic->req_bus->busrq_until);
            }

            break;
        }

        /* ---------------------------------------------------------------- */ 
        /*  PHASE 14:  Do last full BUSRQ.  This phase differs from phases  */
        /*             3..13 only by its cycle length.                      */
        /* ---------------------------------------------------------------- */ 
        case 14:
        {
            int v_dly, h_dly;
            h_dly = stic->raw[0x30] & 7;
            v_dly = stic->raw[0x31] & 7;
            phase_len = 912 - 114*v_dly - h_dly;
            
            if (stic->req_bus->busak <= 
                stic->req_bus->busrq_until - STIC_BUSRQ_MARGIN)
                stic->fifo_ptr += 20;

            memcpy(stic->btab + 20*(stic->phase-3), 
                   stic->btab_sr + stic->fifo_ptr, 20*sizeof(uint_16));
        
            if (stic->vid_enable && stic->req_bus)
            {
                stic->req_bus->intrq       = ASSERT_BUSRQ;
                stic->req_bus->busrq_until = soon + STIC_BUSRQ_HOLD_NORMAL;
                stic->req_bus->next_busrq  = soon + phase_len;
                stic->req_bus->busak       = 0;
//jzp_printf("asserted BUSRQ(13), now = %d, soon = %d, len = %d, until = %d\n", (int)now, (int)soon, (int)len, (int)stic->req_bus->busrq_until);
            }

            break;
        }

        /* ---------------------------------------------------------------- */ 
        /*  PHASE 15:  Do partial BUSRQ.  This BUSRQ happens only if the    */
        /*             vertical delay is zero.                              */
        /* ---------------------------------------------------------------- */ 
        case 15:
        {
            int v_dly = stic->raw[0x31] & 7;

            if (stic->vid_enable && (v_dly == 0) && stic->req_bus)
            {
                stic->req_bus->intrq       = ASSERT_BUSRQ;
                stic->req_bus->busrq_until = soon + STIC_BUSRQ_HOLD_EXTRA;
                stic->req_bus->next_busrq  = ~0ULL;
                stic->req_bus->busak       = 0;
//jzp_printf("asserted BUSRQ(14), now = %d, soon = %d, len = %d, until = %d\n", (int)now, (int)soon, (int)len, (int)stic->req_bus->busrq_until);
            }
            phase_len = 57 + 17;
            new_phase = 0;
            break;
        }


        /* ---------------------------------------------------------------- */ 
        /*  Not supposed to happen.                                         */
        /* ---------------------------------------------------------------- */ 
        default:
        {
            fprintf(stderr, "FATAL ERROR in STIC, phase = %d\n", stic->phase);
            exit(1);
            break;
        }
    }


    stic->stic_cr.min_tick = 1;
    stic->stic_cr.max_tick = phase_len;
    stic->next_phase      += phase_len;
    stic->phase            = new_phase;

//jzp_printf("new_phase = %d, len = %d stic->next_phase = %Ld\n", new_phase, phase_len, stic->next_phase);


    return len;
}


/* ======================================================================== */
/*  This program is free software; you can redistribute it and/or modify    */
/*  it under the terms of the GNU General Public License as published by    */
/*  the Free Software Foundation; either version 2 of the License, or       */
/*  (at your option) any later version.                                     */
/*                                                                          */
/*  This program is distributed in the hope that it will be useful,         */
/*  but WITHOUT ANY WARRANTY; without even the implied warranty of          */
/*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU       */
/*  General Public License for more details.                                */
/*                                                                          */
/*  You should have received a copy of the GNU General Public License       */
/*  along with this program; if not, write to the Free Software             */
/*  Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.               */
/* ======================================================================== */
/*                  Copyright (c) 2003, Joseph Zbiciak                      */
/* ======================================================================== */
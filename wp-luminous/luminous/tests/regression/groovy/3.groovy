

 /*
lines1
               A
               /\
            O .--. D
             / \/ \
         N  .--P---. E
           / \/ \ / \
        M .--U---Q---. F
         / \ / \/ \ / \
      L .-- T---S---R--. G
       / \ / \ / \ /\ / \
      .---.---.---.--.---.
    C     K   J   I  H    B




lines2
                 (A)
                 /\
               / /\ \
            /   /  \   \
          /    /    \    \
        /     /      \     \
      /   (C)/        \(D)   \
 (B)/_______/__________\_______\(E)
    |  \__ /            \ __/  |
    |  (F)X__          __X(G)  |
    |    /   \___  ___/   \    |
    |   /     ___><___     \   |
    |  /   __/   (H)  \__   \  |
    | / __/              \__ \ |
    |/_/____________________\_\|
 (I)                            (J)

 */

 lines2 = ['ae',
           'adgj',
           'acfi',
           'ab',
           'bcde',
           'bfhj',
           'bi',
           'ij',
           'ihge',
           'je'
           ]


 lines1 = ['adefgb',
         'aonmlc',
         'bhijkc',
         'do',
         'dputk',
         'epn',
         'eqsj',
         'fri',
         'fqum',
         'gh',
         'grstl',
         'hrqpo',
         'isun',
         'jtm',
         'kl'
         ]

//  Echo Lines


def computeTriangles =
{ lines ->

    println()

    // Initialize
    count = 0
    size = lines.size
    println "find triangles, $count, $size"


    for (pt1 in 'a'..'u')
    {
        for (pt2 in 'b'..'u')
        {
            for (pt3 in 'c'..'u')
            {
                line = lines.grep(~/(.*$pt1.*$pt2.*|.*$pt2.*$pt3.*|.*$pt1.*$pt3.*)/)
                if (line.size == 3)
                {
                    println (++count + ": $pt1,$pt2,$pt3 : " + line )
                }
            }
        }
    }
}

computeTriangles (lines1)
computeTriangles (lines2)


//  Termination

println ("Terminated Normally") 

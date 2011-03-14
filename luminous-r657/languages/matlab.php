<?php

/* TODO: most of these regexes should be grouped together alphabetically */

class LuminousGrammarMATLAB extends LuminousGrammar
{

  public $keywords = array('break', 'case', 'catch', 'classdef', 'continue',
    'else', 'elseif','end', 'for', 'function', 'global', 'if', 'otherwise', 
    'parfor', 'persistent', 'return', 'spmd', 'switch', 'try', 'while');
    
  public $functions  = array(
    
    // Maths functions
    //  http://www.mathworks.co.uk/help/techdoc/ref/f16-5872.html    
    'disp','display',
    'is(?:column|empty|equalwithequalnans|finite|float|inf|integer|logical|matrix|nan|numeric|row|scalar|sparse|vector)',
    'length', 'max', 'min', 'ndims', 'numel', 'size', 'blkdiag', 'diag', 
    'eye', 'freqspace', 'ind2sub', 'linspace', 'logspace', 'meshgrid',
    'ndgrid', 'ones', 'rand[ni]?', 'RandStream', 'sub2ind', 'zeros',
    'accumarray', 'arrayfun', 'bsxfun', 'cast', 'cross', 'cumprod', 'cumsum',
     'dot', 'idivide', 'kron', 'prod', 'sum', 'surfnorm', 'tril', 'triu',
     'blkdiag', 'cat', 'circshift', 'diag', 'end', 'flipdim', 'fliplr',
     'flipud', 'horzcat', 'inline', 'ipermute', 'permute', 'repmat',
     'reshape', 'rot90', 'shiftdim', 'sort', 'sortrows', 'squeeze',
     'vectorize', 'vertcat', 'compan', 'gallery', 'hadamard', 'hankel',
     'hilb', 'invhilb', 'magic', 'pascal', 'rosser', 'toeplitz', 'vander',
     'wilkinson', 'cond', 'condeig', 'det', 'norm', 'normest', 'null', 'orth',
     'rank', 'rcond', 'rref', 'subspace', 'trace', 'chol', 'cholinc', 'cond',
    'condest', 'funm', 'ilu', 'inv', 'ldl', 'linsolve', 'lscov', 'lsqnonneg',
    'lu', 'luinc', 'pinv', 'qr', 'rcond', 'balance', 'cdf2rdf', 'condeig', 
    'eigs?', 'gsvd', 'hess', 'ordeig', 'ordqz', 'ordschur', 'poly(?:eig)?',
    'rsf2csf', 'schur', 'sqrtm', 'ss2tf', 'svds?', 'expm', 'logm', 'sqrtm',
    'balance', 'cdf2rdf', 'chol(?:inc|update)', 'gsvd', 'ilu', 'ldl', 'lu',
    'luinc', 'planerot', 'qr(?:delete|insert|update)', 'qz', 'rsf2csf',
    'acos[dh]?', 'acot[dh]?', 'acsc[dh]?', 'asec[dh]?', 'asin[dh]?', 
    'atan[2dh]?', 'cos[dh]?', 'cot[dh]?', 'csc[dh]?', 'hypot', 'sec[dh]?',
    'sin[dh]?', 'tan[dh]', 'exp(?:m1)?', 'log(10|1p|2)?', 'nextpow2',
    'nthroot', 'pow2', 'power', 'real(?:log|pow|sqrt)', 'sqrt', 'abs',
    'angle', 'complex', 'conj', 'cplxpair', 'imag', 'isreal', 'real', 'sign',
    'unwrap', 'Rounding', 'ceil', 'fix', 'floor', 'idivide', 'mod', 'rem',
     'round', 'factor', 'factorial', 'gcd', 'isprime', 'lcm', 'nchoosek',
     'perms', 'primes', 'rats?', 'conv', 'deconv', 'poly(der|eig|fit|int|valm?)?',
    'residue', 'roots', 'dsearch', 'dsearchn', 'griddata', 'griddata3',
    'griddatan', 'interp(1q?|[23])', 'interpft', 'interpn', 'meshgrid',
    'mkpp', 'ndgrid', 'padecoef', 'pchip', 'ppval', 'spline',
    'TriScatteredInterp', 'tsearch', 'tsearchn', 'unmkpp', 'baryToCart',
    'cartToBary', 'circumcenters', 'delaunay[3n]?', 'DelaunayTri',
    'edgeAttachments', 'edges', 'faceNormals', 'featureEdges',
    'freeBoundary', 'incenters', 'inOutStatus', 'isEdge', 'neighbors',
    'size', 'tetramesh', 'tri(?:mesh|plot|surf)', 'vertexAttachments',
    'convexHull', 'convhulln?', 'patch', 'trisurf', 'Voronoi', 'patch',
    'voronoi(?:Diagram|n)?', 'meshgrid', 'ndgrid', 'cart2(pol|sph)',
    'pol2cart', 'sph2cart', 'decic', 'deval', 'ode\d+[stb]*',
    'ode(?:file|get|set|extend)', 'dde(?:23|[sg]et|sd)', 'deval', 'bvp[45]c',
    'bvp(?:[sg]et|init|xtend)', 'deval', 'pdepe', 'pdeval', 'fminbnd',
    'fminsearch', 'fzero', 'lsqnonneg', 'optim[sg]et', 'dblquad',
    'quad(?:2d|gk|l|v)?', 'triplequad', 'airy', 'bessel[hijky]', 
    'beta(?:inc(?:inv)?)?', 'betaln', 'ellip(?:j|ke)', 'erfc(?:inv|x|inv)',
    'erfinv', 'expint', 'gamma(?:inc|incinv|ln)', 'legendre', 'psi',
    'sp(diags|eye|randn?|randsym)', 'find', 'full', 'sparse', 'spconvert',
    'issparse', 'nnz', 'nonzeros', 'nzmax', 'sp(alloc|fun|ones|parms|y)', 
    'amd', 'colamd', 'colperm', 'dmperm', 'ldl', 'randperm', 'symamd',
    'symrcm', 'cholinc', 'condest', 'eigs', 'ilu', 'luinc', 'normest',
    'spaugment', 'sprank', 'svds', 'bicg(?:stabl?)?', 'cgs', 'gmres', 'lsqr',
    'minres', 'pcg', 'qmr', 'symmlq', 'tfqmr', 'etree', 'etreeplot', 'gplot',
    'symbfact', 'treelayout', 'treeplot', 'unmesh',
    
    // Data conversion
    // http://www.mathworks.com/help/techdoc/ref/f16-42340.html#f16-52710
    'cast', 'double', 'int(?:8|16|32|64)', 'single', 'typecast', 
    'uint(?:8|16|32|64)', 'base2dec', 'bin2dec', 'hex2(?:dec|num)', 
    'str2(?:double|num)', 'unicode2native', 'char', 'dec2(?:base|bin|hex)',
    'int2str', 'mat2str',  'native2unicode', 'num2str', 'cell2(?:mat|struct)',
    'datestr', 'func2str', 'logical', 'mat2cell', 'num2(?:cell|hex)',
    'str2(?:func|mat)', 'struct2cell'
    );
    
    
  public $types = array('eps', 'i', 'Inf', 'int(?:max|min)', 'j', 'NaN', 
    'pi', 'real(?:max|min)');
    
    
  public function __construct()
  {
    $this->SetInfoAuthor( 
    array('name'=>'Mark Watkinson', 'email'=>'markwatkinson@gmail.com',
    'website'=>'http://www.asgaard.co.uk'));
    $this->SetInfoLanguage('matlab');
    $this->SetInfoVersion('r657');    
    
    
    $this->delimited_types = array(
      new LuminousDelimiterRule(0, 'COMMENT_', 0,
        '%{', '%}'),
      luminous_generic_comment_sl("%") ,
      // the lookbehind is because MATLAB allows X-prime as X', it's a 
      // matrix operation. Transpose IIRC.
      new LuminousDelimiterRule(0, 'STRING', LUMINOUS_COMPLETE|LUMINOUS_REGEX,
      $regex = "/
        (?<![\w\)\]\}])
        '
          (?:
            [^']+|''
          )*
        '
        /x", null, 'luminous_type_callback_sql_single_quotes')
 
    ); 
    $this->state_transitions = array(
      'GLOBAL' => array('COMMENT_', 'COMMENT', 'STRING'),
      'COMMENT_' => array('COMMENT_'),
    );
    $this->state_type_mappings = array(
      'COMMENT_' => 'COMMENT'
    );
    $this->SetSimpleTypeRules();
    
  }
}

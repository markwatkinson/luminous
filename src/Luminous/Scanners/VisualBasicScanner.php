<?php

namespace Luminous\Scanners;

use Luminous\Core\Scanners\SimpleScanner;

/*
 * VB.NET
 *
 * Language spec:
 * http://msdn.microsoft.com/en-us/library/aa712050(v=vs.71).aspx
 *
 * TODO: IIRC vb can be embedded in asp pages like php or ruby on rails,
 * and XML literals: these are a little bit confusing, something
 * like "<xyz>.something" appears to be a valid XML fragment (i.e. the <xyz>
 * is a complete fragment), but at other times, the fragment would run until
 * the root tag is popped. Need to find a proper description of the grammar
 * to figure it out
 */
class VisualBasicScanner extends SimpleScanner
{
    public $caseSensitive = false;

    public function init()
    {
        $this->addPattern('PREPROCESSOR', "/^[\t ]*#.*/m");
        $this->addPattern('COMMENT', "/'.*/");

        $this->addPattern('COMMENT', '/\\bREM\\b.*/i');
        // float
        $this->addPattern(
            'NUMERIC',
            '/ (?<!\d)
                \d+\.\d+ (?: e[+\\-]?\d+)?
                |\.\d+ (?: e[+\\-]?\d+)?
                | \d+ e[+\\-]?\d+
            /xi'
        );
        // int
        $this->addPattern(
            'NUMERIC',
            '/ (?:
                &H[0-9a-f]+
                | &O[0-7]+
                | (?<!\d)\d+
            ) [SIL]*/ix'
        );

        $this->addPattern('CHARACTER', '/"(?:""|.)"c/i');

        $this->addPattern('STRING', '/" (?> [^"]+ | "" )* ($|")/x');
        // in theory we should also match unicode quote chars
        // in reality, well, I read the php docs and I have no idea if it's
        // even possible.
        // The chars are:
        // http://www.fileformat.info/info/unicode/char/201c/index.htm
        // and
        // http://www.fileformat.info/info/unicode/char/201d/index.htm

        // date literals, this isn't as discriminating as the grammar specifies.
        $this->addPattern('VALUE', "/#[ \t][^#\n]*[ \t]#/");

        $this->addPattern('OPERATOR', '/[&*+\\-\\/\\\\^<=>,\\.]+/');

        // http://msdn.microsoft.com/en-us/library/aa711645(v=VS.71).aspx
        // XXX: it warns about ! being ambiguous but I don't see how it can be
        // ambiguous if we use this regex?
        $this->addPattern('IDENT', '/[a-z_]\w*[%&@!#$]?/i');

        // we'll borrow C#'s list of types (ie modules, classes, etc)
        $this->addIdentifierMapping('VALUE', array('False', 'Nothing', 'True'));
        $this->addIdentifierMapping('OPERATOR', array(
            'AddressOf',
            'And',
            'AndAlso',
            'GetType',
            'GetXmlNamespace',
            'Is',
            'IsFalse',
            'IsNot',
            'IsTrue',
            'Mod',
            'Not',
            'Or',
            'OrElse',
            'TypeOf',
            'Xor'
        ));
        $this->addIdentifierMapping('TYPE', array(
            'Boolean',
            'Byte',
            'CBool',
            'Cbyte',
            'CChar',
            'CDate',
            'CDbl',
            'CDec',
            'Char',
            'CInt',
            'CLng',
            'CObj',
            'CShort',
            'CSng',
            'CStr',
            'CType',
            'Date',
            'Decimal',
            'Double',
            'Integer',
            'Long',
            'Object',
            'Short',
            'String'
        ));
        $this->addIdentifierMapping('KEYWORD', array(
            'AddHandler',
            'Alias',
            'Ansi',
            'As',
            'Assembly',
            'Auto',
            'ByRef',
            'ByVal',
            'Call',
            'Case',
            'Catch',
            'Class',
            'Const',
            'Declare',
            'Default',
            'Delegate',
            'Dim',
            'DirectCast',
            'Do',
            'Each',
            'Else',
            'ElseIf',
            'End',
            'EndIf',
            'Enum',
            'Erase',
            'Error',
            'Event',
            'Exit',
            'Finally',
            'For',
            'Friend',
            'Function',
            'Get',
            'GetType',
            'GoSub',
            'GoTo',
            'Handles',
            'If',
            'Implements',
            'Imports',
            'In',
            'Inherits',
            'Interface',
            'Let',
            'Lib',
            'Like',
            'Loop',
            'Me',
            'Module',
            'MustInherit',
            'MustOverride',
            'MyBase',
            'MyClass',
            'Namespace',
            'New',
            'Next',
            'Nothing',
            'NotInheritable',
            'NotOverridable',
            'On',
            'Option',
            'Optional',
            'OrElse',
            'Overloads',
            'Overridable',
            'Overrides',
            'ParamArray',
            'Preserve',
            'Private',
            'Property',
            'Protected',
            'Public',
            'RaiseEvent',
            'ReadOnly',
            'ReDim',
            'RemoveHandler',
            'Resume',
            'Return',
            'Select',
            'Set',
            'Shadows',
            'Shared',
            'Single',
            'Static',
            'Step',
            'Stop',
            'Structure',
            'Sub',
            'SyncLock',
            'Then',
            'Throw',
            'To',
            'Try',
            'Unicode',
            'Until',
            'Variant',
            'Wend',
            'When',
            'While',
            'With',
            'WithEvents',
            'WriteOnly'
        ));
        $this->addIdentifierMapping('TYPE', array(
            // primatives
            'bool',
            'byte',
            'char',
            'const',
            'double',
            'decimal',
            'enum',
            'float',
            'int',
            'long',
            'object',
            'sbyte',
            'short',
            'string',
            'uint',
            'ulong',
            'ushort',
            'void',
            // system
            'ArgIterator',
            'ArraySegment',
            'Boolean',
            'Byte',
            'Char',
            'ConsoleKeyInfo',
            'DateTime',
            'DateTimeOffset',
            'Decimal',
            'Double',
            'Guid',
            'Int16',
            'Int32',
            'Int64',
            'IntPtr',
            'ModuleHandle',
            'Nullable',
            'RuntimeArgumentHandle',
            'RuntimeFieldHandle',
            'RuntimeMethodHandle',
            'RuntimeTypeHandle',
            'SByte',
            'Single',
            'TimeSpan',
            'TimeZoneInfo',
            'TypedReference',
            'UInt16',
            'UInt32',
            'UInt64',
            'UIntPtr',
            'Void',

            // also system
            'AccessViolationException',
            'ActivationContext',
            'Activator',
            'AggregateException',
            'AppDomain',
            'AppDomainManager',
            'AppDomainSetup',
            'AppDomainUnloadedException',
            'ApplicationException',
            'ApplicationId',
            'ApplicationIdentity',
            'ArgumentException',
            'ArgumentNullException',
            'ArgumentOutOfRangeException',
            'ArithmeticException',
            'Array',
            'ArrayTypeMismatchException',
            'AssemblyLoadEventArgs',
            'Attribute',
            'AttributeUsageAttribute',
            'BadImageFormatException',
            'BitConverter',
            'Buffer',
            'CannotUnloadAppDomainException',
            'CharEnumerator',
            'CLSCompliantAttribute',
            'Console',
            'ConsoleCancelEventArgs',
            'ContextBoundObject',
            'ContextMarshalException',
            'ContextStaticAttribute',
            'Convert',
            'DataMisalignedException',
            'DBNull',
            'Delegate',
            'DivideByZeroException',
            'DllNotFoundException',
            'DuplicateWaitObjectException',
            'EntryPointNotFoundException',
            'Enum',
            'Environment',
            'EventArgs',
            'Exception',
            'ExecutionEngineException',
            'FieldAccessException',
            'FileStyleUriParser',
            'FlagsAttribute',
            'FormatException',
            'FtpStyleUriParser',
            'GC',
            'GenericUriParser',
            'GopherStyleUriParser',
            'HttpStyleUriParser',
            'IndexOutOfRangeException',
            'InsufficientExecutionStackException',
            'InsufficientMemoryException',
            'InvalidCastException',
            'InvalidOperationException',
            'InvalidProgramException',
            'InvalidTimeZoneException',
            'Lazy',
            'LdapStyleUriParser',
            'LoaderOptimizationAttribute',
            'LocalDataStoreSlot',
            'MarshalByRefObject',
            'Math',
            'MemberAccessException',
            'MethodAccessException',
            'MissingFieldException',
            'MissingMemberException',
            'MissingMethodException',
            'MTAThreadAttribute',
            'MulticastDelegate',
            'MulticastNotSupportedException',
            'NetPipeStyleUriParser',
            'NetTcpStyleUriParser',
            'NewsStyleUriParser',
            'NonSerializedAttribute',
            'NotFiniteNumberException',
            'NotImplementedException',
            'NotSupportedException',
            'Nullable',
            'NullReferenceException',
            'Object',
            'ObjectDisposedException',
            'ObsoleteAttribute',
            'OperatingSystem',
            'OperationCanceledException',
            'OutOfMemoryException',
            'OverflowException',
            'ParamArrayAttribute',
            'PlatformNotSupportedException',
            'Random',
            'RankException',
            'ResolveEventArgs',
            'SerializableAttribute',
            'StackOverflowException',
            'STAThreadAttribute',
            'String',
            'StringComparer',
            'SystemException',
            'ThreadStaticAttribute',
            'TimeoutException',
            'TimeZone',
            'TimeZoneInfo',
            'TimeZoneInfo',
            'TimeZoneNotFoundException',
            'Tuple',
            'Type',
            'TypeAccessException',
            'TypeInitializationException',
            'TypeLoadException',
            'TypeUnloadedException',
            'UnauthorizedAccessException',
            'UnhandledExceptionEventArgs',
            'Uri',
            'UriBuilder',
            'UriFormatException',
            'UriParser',
            'UriTemplate',
            'UriTemplateEquivalenceComparer',
            'UriTemplateMatch',
            'UriTemplateMatchException',
            'UriTemplateTable',
            'UriTypeConverter',
            'ValueType',
            'Version',
            'WeakReference',
            // system.collections
            'ArrayList',
            'BitArray',
            'CaseInsensitiveComparer',
            'CaseInsensitiveHashCodeProvider',
            'CollectionBase',
            'Comparer',
            'DictionaryBase',
            'DictionaryEntry',
            'Hashtable',
            'ICollection',
            'IComparer',
            'IDictionary',
            'IDictionaryEnumerator',
            'IEnumerable',
            'IEnumerator',
            'IEqualityComparer',
            'IHashCodeProvider',
            'IList',
            'IStructuralComparable',
            'IStructuralEquatable',
            'Queue',
            'ReadOnlyCollectionBase',
            'SortedList',
            'Stack',
            'StructuralComparisons',

            // System.Collections.Generic
            'Comparer',
            'Dictionary',
            'EqualityComparer',
            'HashSet',
            'ICollection',
            'IComparer',
            'IDictionary',
            'IEnumerable',
            'IEnumerator',
            'IEqualityComparer',
            'IList',
            'IReadOnlyCollection',
            'IReadOnlyDictionary',
            'IReadOnlyList',
            'ISet',
            'KeyedByTypeCollection',
            'KeyNotFoundException',
            'KeyValuePair',
            'LinkedList',
            'LinkedListNode',
            'List',
            'Queue',
            'SortedDictionary',
            'SortedList',
            'SortedSet',
            'Stack',
            'SynchronizedCollection',
            'SynchronizedKeyedCollection',
            'SynchronizedReadOnlyCollection',

            // system.io
            'BinaryReader',
            'BinaryWriter',
            'BufferedStream',
            'Directory',
            'DirectoryInfo',
            'DirectoryNotFoundException',
            'DriveInfo',
            'DriveNotFoundException',
            'EndOfStreamException',
            'ErrorEventArgs',
            'File',
            'FileFormatException',
            'FileInfo',
            'FileLoadException',
            'FileNotFoundException',
            'FileStream',
            'FileSystemEventArgs',
            'FileSystemInfo',
            'FileSystemWatcher',
            'InternalBufferOverflowException',
            'InvalidDataException',
            'IODescriptionAttribute',
            'IOException',
            'MemoryStream',
            'Path',
            'PathTooLongException',
            'PipeException',
            'RenamedEventArgs',
            'Stream',
            'StreamReader',
            'StreamWriter',
            'StringReader',
            'StringWriter',
            'TextReader',
            'TextWriter',
            'UnmanagedMemoryAccessor',
            'UnmanagedMemoryStream',
        ));
    }

    public static function guessLanguage($src, $info)
    {
        $p = 0.0;
        if (preg_match('/^Imports\s+System/i', $src)) {
            $p += 0.1;
        }
        if (preg_match('/Dim\s+\w+\s+As\s+/i', $src)) {
            $p += 0.2;
        }
        if (preg_match('/(Public|Private|Protected)\s+Sub\s+/i', $src)) {
            $p += 0.1;
        }
        return $p;
    }
}

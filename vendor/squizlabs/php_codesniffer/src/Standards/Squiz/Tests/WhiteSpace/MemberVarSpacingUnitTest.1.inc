<?php
class MyClass
{
    public $var1 = 'value';


    public ?int $var2 = 'value';

    public $var3 = 'value';

}//end class


interface MyInterface
{

    public $var1 = 'value';


    public ?Folder\ClassName $var2 = 'value';

    protected $var3 = 'value';
}//end interface


class MyClass
{


    public string $var1 = 'value';

    private string $var2 = 'value';


    protected $var3 = 'value';


}//end class



class MyClass
{
    public $var1 = 'value';
}//end class


interface MyInterface
{
    public $var1 = 'value';
    function myFunction();
}//end interface


class MyClass
{

    /**
     * The actions that this wizard step requires.
     *
     * @var   array
     * @since 4.0.0
     */
    protected $actions = array();


    /**
     * TRUE if this step should be performed after the asset is created.
     *
     * @var   boolean
     * @since 4.0.0
     */
    protected $postStep = FALSE;


}//end class

class MyClass
{
    /**
     * The actions that this wizard step requires.
     *
     * @var   array
     * @since 4.0.0
     */
    protected $actions = array();

}//end class

class MyClass
{

    /**
     * The actions that this wizard step requires.
     *
     * @var   array
     * @since 4.0.0
     */
    var $actions = array();

    /**
     * The actions that this wizard step requires.
     *
     * @var   array
     * @since 4.0.0
     */

    protected $actions = array();
    /**
     * The actions that this wizard step requires.
     *
     * @var   array
     * @since 4.0.0
     */
    protected $actions = array();

}//end class

class Foo
{

    private $foo; // comment

    private $bar;

}

class Foo
{

    private $foo; // comment

    /**
     * @var type
     */
    private $bar;

}

class Foo
{

    /**
     * @var integer
     */
    private $foo; // comment

    private $bar;

    //  here comes the comment

    private $caseStudy = null;

}

// phpcs:set Squiz.WhiteSpace.MemberVarSpacing spacing 2

class MyClass
{
    public $var1 = 'value';


    public $var2 = 'value';

    public $var3 = 'value';

}//end class

// phpcs:set Squiz.WhiteSpace.MemberVarSpacing spacing 1
// phpcs:set Squiz.WhiteSpace.MemberVarSpacing spacingBeforeFirst 0

class MyClass
{
    public $var1 = 'value';


    public $var2 = 'value';

    public $var3 = 'value';

}//end class

class MyClass
{

    public $var1 = 'value';

    public $var2 = 'value';

    public $var3 = 'value';

}//end class

// phpcs:set Squiz.WhiteSpace.MemberVarSpacing spacing 0

class MyClass
{
    public $var1 = 'value';

    public $var2 = 'value';
    public $var3 = 'value';

}//end class

interface MyInterface
{

    /* testing */
    public $var1 = 'value';

    public $var2 = 'value';
    public $var3 = 'value';

}//end class

// phpcs:set Squiz.WhiteSpace.MemberVarSpacing spacing 0
// phpcs:set Squiz.WhiteSpace.MemberVarSpacing spacingBeforeFirst 0

class phpcsCommentTest {


    // phpcs:disable Standard.Category.Sniff

    public $var1 = 'value';


    // phpcs:enable Standard.Category.Sniff

    public $var1 = 'value';

}

// phpcs:set Squiz.WhiteSpace.MemberVarSpacing spacing 1
// phpcs:set Squiz.WhiteSpace.MemberVarSpacing spacingBeforeFirst 1

class phpcsCommentTest {


    // phpcs:disable Standard.Category.Sniff

    public $var1 = 'value';


    // phpcs:enable Standard.Category.Sniff

    public $var1 = 'value';

}

class MyOtherClass
{
    public
        $varK = array( 'a', 'b' );
    protected static
        $varK,
        $varL,
        $varM;



    private
        $varO = true,
        $varP = array( 'a' => 'a', 'b' => 'b' ),
        $varQ = 'string',
        $varR = 123;
}

// Make sure the determination of whether a property is the first property or not is done correctly.
class ClassUsingSimpleTraits
{
    use HelloWorld;


    /* comment */
    public $firstVar = array( 'a', 'b' );
    protected $secondVar = true;
}

class ClassUsingComplexTraits
{
    use A, B {
        B::smallTalk insteadof A;
        A::bigTalk insteadof B;
    }



    public $firstVar = array( 'a', 'b' );


    /* comment */
    protected $secondVar = true;
}

class Foo
{


    private function foo()
    {
    }


    /* no error here because after function */
    private $bar = false;
}

class CommentedOutCodeAtStartOfClass {

    /**
     * Description.
     *
     * @var bool
     */
    //public $commented_out_property = true;

    /**
     * Description.
     *
     * @var bool
     */
    public $property = true;
}

class CommentedOutCodeAtStartOfClassNoBlankLine {

    // phpcs:disable Stnd.Cat.Sniff -- For reasons.
    /**
     * Description.
     *
     * @var bool
     */
    public $property = true;
}

class HasAttributes
{
    /**
     * Short description of the member variable.
     *
     * @var array
     */

    #[ORM\Id]#[ORM\Column("integer")]

    private $id;


    /**
     * Short description of the member variable.
     *
     * @var array
     */
    #[ORM\GeneratedValue]

    #[ORM\Column(ORM\Column::T_INTEGER)]
    protected $height;

    #[SingleAttribute]
    protected $propertySingle;

    #[FirstAttribute]
    #[SecondAttribute]
    protected $propertyDouble;
    #[ThirdAttribute]
    protected $propertyWithoutSpacing;
}

enum SomeEnum
{
    // Enum cannot have properties

    case ONE = 'one';
}

class SupportReadonlyProperties {
    readonly int $readonlyA;
    public readonly string $publicReadonly;
    readonly bool $readonlyB;
    readonly private bool $readonlyPrivate;
}

class NoPreambleMultilineDeclaration {
    public
    static
    int $prop = 1;
}

class MultipleBlankLinesInPreAmble {

    /**
     * Docblock.
     */

    #[MyAttribute]


    #[

        BlankLinesWithinAnAttributeShouldBeLeftAlone

    ]

    public $prop;
}

final class BlankLinesBetweenVsAttributesWithoutCommentIssueSquiz3594
{

    // PHPCS can fix blank lines for the first property, but not for the second. (fixed now)
    #[SingleAttribute]

    public $property1;
    #[SingleAttribute]

    public $property2;
}

class PHP84FinalProperties {
    final int $finalA;

    /**
     * Docblock
     */
    public final string $publicfinal;
    #[AnAttribute]
    final bool $finalB;

    final private bool $finalPrivate;
}

class MultilineCommentShouldNotBeSplitUp {
    // This is the first line
    // of a multi-line comment
    // which should be recognized as belonging
    // with the property.
    public $prop;


    /* This is the first line
       of a multi-line comment
       which should be recognized as belonging
       with the property.
     */
    public $prop;
}

class AsymVisibility {
    protected private(set) int $asymProtectedPrivate;

    /**
     * Docblock
     */
    protected(set) final string $asymProtected;
    #[AnAttribute]

    public(set) string|bool $asymPublic;


    private(set) private bool $asymPrivate;
}

<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Helper\Html\Form;

/**
 * @class Ol
 * @brief HTML Forms Ol creation helpers
 *
 * @method      $this separator(string $separator)
 * @method      $this items(array $items)
 * @method      $this format(string $format)
 * @method      $this start(string $start)
 * @method      $this reversed(bool $reversed)
 *
 * @property    string $separator
 * @property    array $items
 * @property    string $format
 * @property    string $start
 * @property    bool $reversed
 */
class Ol extends Component
{
    private const DEFAULT_ELEMENT = 'ol';

    /**
     * Constructs a new instance.
     *
     * @param      string|array{0: string, 1?: string}|null     $id       The identifier
     * @param      string                                       $element  The element
     */
    public function __construct($id = null, ?string $element = null)
    {
        parent::__construct(self::class, $element ?? self::DEFAULT_ELEMENT);
        if ($id !== null) {
            $this->setIdentifier($id);
        }
    }

    /**
     * Renders the HTML component.
     *
     * @param   string  $format     sprintf() format applied for each items/fields ('%s' by default)
     *
     * @return     string
     */
    public function render(?string $format = null): string
    {
        $buffer = '<' . ($this->getElement() ?? self::DEFAULT_ELEMENT) .
            (isset($this->reversed) && $this->reversed ? ' reversed' : '') .
            (isset($this->start) ? ' start="' . $this->start . '"' : '') .
            (isset($this->type) ? ' type="' . $this->type . '"' : '') .
            $this->renderCommonAttributes() . '>' . "\n";

        $first = true;
        $format ??= ($this->format ?? '%s');

        // Cope with items
        if (isset($this->items)) {
            $first = true;
            foreach ($this->items as $item) {
                if (!$first && $this->separator) {
                    $buffer .= (string) $this->separator;
                }
                $buffer .= sprintf($format, $item->render());
                $first = false;
            }
        }

        $buffer .= '</' . ($this->getElement() ?? self::DEFAULT_ELEMENT) . '>' . "\n";

        return $buffer;
    }

    /**
     * Gets the default element.
     *
     * @return     string  The default element.
     */
    public function getDefaultElement(): string
    {
        return self::DEFAULT_ELEMENT;
    }
}

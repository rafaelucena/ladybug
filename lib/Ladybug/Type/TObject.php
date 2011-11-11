<?php
/*
 * Ladybug: Simple and Extensible PHP Dumper
 * 
 * Type/TObject variable type
 *
 * (c) Raúl Fraile Beneyto <raulfraile@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Ladybug\Type;

use Ladybug\Variable;
use Ladybug\CLIColors;

class TObject extends Variable {
    
    protected $class_name;
    protected $class_constants;
    protected $object_properties;
    protected $class_static_properties;
    protected $class_methods;
    protected $object_custom_data;
    protected $class_file;
    protected $class_interfaces;
    protected $class_namespace;
    protected $class_parent;
    
    protected $is_leaf;
    
    
    public function __construct($var, $level = 0) {
        parent::__construct('object', $var, $level);
        
        
        
        $this->class_name = get_class($var);
       
        if ($this->level < \Ladybug\Dumper::MAX_NESTING_LEVEL_OBJECTS) {
            $this->is_leaf = FALSE;
            
            $reflection_class = new \ReflectionClass($this->class_name); 

            
            $this->class_constants = $reflection_class->getConstants();
            
            //$object_static_properties = $reflection_class->getStaticProperties();
            //$this->object_static_properties_clean = array();
            $class_methods = $reflection_class->getMethods();

            $this->class_file = $reflection_class->getFileName();
            if (empty($this->class_file)) $this->class_file = 'built-in';
            $this->class_interfaces = implode(', ', $reflection_class->getInterfaceNames());
            $this->class_namespace = $reflection_class->getNamespaceName();
            $class_parent_obj = $reflection_class->getParentClass();
            if ($class_parent_obj) $this->class_parent = $class_parent_obj->getName();
            else $this->class_parent = '';

            unset($class_parent_obj); $class_parent_obj = NULL;
            //unset($reflection_class); $reflection_class = NULL;

            // is there a class to show the object info?
            $include_class = $this->getIncludeClass($this->class_name, 'object');

            if (class_exists($include_class)) {
                $custom_dumper = new $include_class($var);
                $this->object_custom_data = $custom_dumper->dump($var);
            }
            else $this->object_custom_data = (array)$var;

            // Custom/array-cast data
            if (!empty($this->object_custom_data) && is_array($this->object_custom_data)) {
                foreach ($this->object_custom_data as &$c) {
                    $c = TFactory::factory($c, $this->level);
                }
            }

            // Class constants
            if (!empty($this->class_constants)) {
                foreach ($this->class_constants as &$c) {
                    $c = TFactory::factory($c, $this->level);
                }
            }

            // Object properties
            $object_properties = $reflection_class->getProperties();
            $this->object_properties = array();
            if (!empty($object_properties)) {
                foreach($object_properties as $property) {
                    if ($property->isPublic()) {
                        $property_value = $property->getValue($this->value);
                        $this->object_properties[$property->getName()] = TFactory::factory($property_value, $this->level);
                    }
                }
            }

            /*if (!empty($this->object_static_properties)) {
                foreach($this->object_static_properties as $property) {
                    if ($property->isPublic()) {
                        $property_value = $property->getValue($this->value);
                        $this->object_static_properties_clean[$property->getName()] = TFactory::factory($property_value, $this->level);
                    }
                }
            }*/
            
            // Class methods
            if (!empty($class_methods)) {
                foreach($class_methods as $k=>$v) {
                    $method = $reflection_class->getMethod($v->name);
                    
                    $method_syntax = '';

                    if ($method->isPublic()) $method_syntax .= '+ ';
                    elseif ($method->isProtected()) $method_syntax .= '# ';
                    elseif ($method->isPrivate()) $method_syntax .= '- ';

                    $method_syntax .= $method->getName();

                    $method_parameters = $method->getParameters();
                    $method_syntax .= '(';
                    $method_parameters_result = array();
                    foreach ($method_parameters as $parameter) {
                        $parameter_result = '';
                        if ($parameter->isOptional()) $parameter_result .= '[';

                        if ($parameter->isPassedByReference()) $parameter_result .= '&';
                        $parameter_result .= '$' . $parameter->getName();

                        if ($parameter->isOptional()) $parameter_result .= ']';

                        $method_parameters_result[] = $parameter_result; 
                    }

                    $method_syntax .= implode(', ', $method_parameters_result);
                    $method_syntax .= ')';

                    $this->class_methods[] = $method_syntax;
                }
                
                sort($this->class_methods, SORT_STRING);
            }
        }
        else $this->is_leaf = TRUE;
    }
    
    public function _renderHTML($array_key = NULL) {
        $label = $this->type . '('.$this->class_name . ')';
        $result = $this->renderTreeSwitcher($label, $array_key);
        
        if (!$this->is_leaf) {
            $result .= '<ol>';
        
            if (!empty($this->object_custom_data)) {
                $result .= '<li>' . $this->renderTreeSwitcher('Data') . '<ol>';

                if (is_array($this->object_custom_data)) {
                    foreach($this->object_custom_data as $k=>&$v) {
                        $result .= '<li>'.$v->render($k).'</li>';
                    }
                }
                else $result .= '<li>'.$this->object_custom_data.'</li>';

                $result .= '</ol></li>';

            }

            // class info
            if (!empty($this->class_file)) {
                $result .= '<li>' . $this->renderTreeSwitcher('Class info') . '<ol>';
                if (!empty($this->class_file)) $result .= '<li>file: '.$this->class_file.'</li>';
                if (!empty($this->class_interfaces)) $result .= '<li>interfaces: '.$this->class_interfaces.'</li>';
                if (!empty($this->class_namespace)) $result .= '<li>namespace: '.$this->class_namespace.'</li>';
                if (!empty($this->class_parent)) $result .= '<li>parent: '.$this->class_parent.'</li>';        
                $result .= '</ol></li>';       
            }

            // constants
            if (!empty($this->class_constants)) {
                $result .= '<li>' . $this->renderTreeSwitcher('Constants') . '<ol>';
                foreach($this->class_constants as $k=>$v) {
                    $result .= '<li>'.$v->render($k).'</li>';
                }
                $result .= '</ol></li>';
            }

            // properties
            if (!empty($this->object_properties)) {
                $result .= '<li>' . $this->renderTreeSwitcher('Public properties') . '<ol>';
                foreach($this->object_properties as $k=>$v) {
                    $result .= '<li>'.$v->render($k).'</li>';
                }
                $result .= '</ol></li>';
            }

            // static properties
            if (!empty($this->class_static_properties)) {
                $result .= '<li>' . $this->renderTreeSwitcher('Static properties') . '<ol>';
                foreach($this->class_static_properties as $k=>$v) {
                    $result .= '<li>'.$v->render($k).'</li>';
                }
                $result .= '</ol></li>';
            }

            // class methods
            if (!empty($this->class_methods)) {
                $result .= '<li>' . $this->renderTreeSwitcher('Methods') . '<ol>';
                foreach($this->class_methods as $v) {
                    $result .= '<li>'.$v.'</li>';
                }
                $result .= '</ol></li>';
            }

            $result .= '</ol>';
        }
        
        return $result;
        
    }
    
    public function _renderCLI($array_key = NULL) {
        $label = $this->type . '('.$this->class_name . ')';
        $result = $this->renderArrayKey($array_key) . CLIColors::getColoredString($label, 'yellow');
        
        if (!$this->is_leaf) {
            
            $result .=  "\n";
            
            if (!empty($this->object_custom_data)) {
                $result .= $this->indentCLI() . CLIColors::getColoredString('Data', NULL, 'magenta') . "\n";

                if (is_array($this->object_custom_data)) {
                    foreach($this->object_custom_data as $k=>&$v) {
                        $result .= $this->indentCLI() .  $v->render($k, 'cli');
                    }
                }
                else $result .= $this->indentCLI() . $this->object_custom_data."\n";

            }

            // class info
            if (!empty($this->class_file)) {
                $result .= $this->indentCLI() . CLIColors::getColoredString('Class info', NULL, 'magenta') . "\n";
                if (!empty($this->class_file)) $result .= $this->indentCLI() . 'File: '.$this->class_file."\n";
                if (!empty($this->class_interfaces)) $result .= $this->indentCLI() . 'Interfaces: '.$this->class_interfaces."\n";
                if (!empty($this->class_namespace)) $result .= $this->indentCLI() . 'Namespace: '.$this->class_namespace."\n";
                if (!empty($this->class_parent)) $result .= $this->indentCLI() . 'Parent: '.$this->class_parent."\n";
            }

            // constants
            $result .= $this->_renderListCLI($this->class_constants, 'Constants');

            // properties
            $result .= $this->_renderListCLI($this->object_properties, 'Public properties');

            // static properties
            $result .= $this->_renderListCLI($this->class_static_properties, 'Static properties');
            
            // methods
            $result .= $this->_renderListCLI($this->class_methods, 'Methods');

        }
        else $result .= "\n";
        
        return $result;
        
    }
    
    private function _renderListCLI(&$list, $title) {
        $result = '';
        
        if (!empty($list)) {
            $result .= $this->indentCLI() . CLIColors::getColoredString($title, NULL, 'magenta') . "\n";
            foreach($list as $k=>$v) {
                $result .= $this->indentCLI();
                
                if (is_string($v)) $result .= $v;
                else $result .= $v->render($k, 'cli');
                    
                $result .= "\n";
            }
        }
            
        return $result;
    }
}
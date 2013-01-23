package dom

/*
 * Node implementation
 *
 * Copyright (c) 2009, Rob Russell
 * Copyright (c) 2010, Jeff Schiller
 */

// TODO: think about how to make this class a bit more generic to promote extensibility
//       (for instance, this class has to know about Attr, Element and Document types to
//        implement NodeName() among other things)

import (
  "container/vector";
  "xml";
)

type _node struct {
  T int; // node type
  p Node; // parent
  c vector.Vector; // children
  n xml.Name; // name
  self Node; // this _node as a Node
}

// internal methods used so that our workhorses can do the real work
func (n *_node) setParent(p Node) {
  n.p = p;
}
func (n *_node) insertChildAt(c Node, i uint) {
  n.c.Insert(int(i), c);
}
func (n *_node) removeChild(c Node) {
  for i := n.c.Len()-1 ; i >= 0 ; i-- {
    if n.c.At(i).(Node) == c {
      n.c.Delete(i);
      break;
    }
  }
}

func (n *_node) NodeName() string {
  switch n.T {
    case 1: return n.n.Local;
    case 2: return n.n.Local;
    case 9: return "#document";
  }
  return "Node.NodeName() not implemented";
}
func (n *_node) NodeValue() string { return "Node.NodeValue() not implemented"; }
func (n *_node) TagName() string { return n.NodeName(); }
func (n *_node) NodeType() int { return n.T; }
func (n *_node) AppendChild(c Node) Node { return appendChild(n,c); }
func (n *_node) RemoveChild(c Node) Node { return removeChild(n,c); }
func (n *_node) ChildNodes() NodeList { return newChildNodelist(n); }
func (n *_node) ParentNode() Node { return n.p; }
func (n *_node) Attributes() NamedNodeMap { return NamedNodeMap(nil); }
func (n *_node) HasChildNodes() (b bool) {
  b = false;
  if n.c.Len() > 0 {
    b = true;
  }
  return;
}

// has to be package-scoped because of
func ownerDocument(n Node) (d Document) {
  d = nil;

  for n!=nil {
    if n.NodeType()==9 {
      return n.(Document);
    }
    n = n.ParentNode();
  }
  return Document(nil);
}

//func (n *_node) OwnerDocument(n Node) (d Document) {
  //d = nil;
  //p := n.p;
  //
  //for p!=nil {
  //  if p.NodeType()==9 {
  //    return (*_doc)(p);
  //  }
  //  p = n.p;
  //}
//  return Document(nil);
//}


func newNode(_t int) (n *_node) {
  n = new(_node);
  n.T = _t;
  n.self = Node(n)
  return;
}


func (p *_node) InsertBefore(nc Node, rc Node) Node {
  if rc == nil {
    // if refChild is null, insert newChild at the end of the list of children.
    return appendChild(p,nc)
  } else if rc == nc {
    // inserting a node before itself is implementation dependent
    return nc
  }
  // if newChild is already in the tree somewhere,
  // remove it before reparenting
  if nc.ParentNode() != nil {
    removeChild(nc.ParentNode(), nc)
  }
  // find refChild & insert
  nl := p.ChildNodes()
  i := nl.Length()
  for cix := uint(0); cix < i; cix++ {
    if nl.Item(cix) == rc {
      p.insertChildAt(nc, cix)
      nc.setParent(p)
    }
  }
  return nc;
}

func (p *_node) ReplaceChild(nc Node, rc Node) Node {
  p.InsertBefore(nc, rc);
  return p.RemoveChild(rc);
}
func (p *_node) FirstChild() Node {
  res := Node(nil)
  if p.c.Len() > 0 {
    res = p.c.At(0).(Node)
  }
  return res
}
func (p *_node) LastChild() Node {
  res := Node(nil)
  if p.c.Len() > 0 {
    res = p.c.At(p.c.Len()-1).(Node)
  }
  return res
}
func (n *_node) PreviousSibling() Node {
  children := n.p.ChildNodes()
  for i := children.Length()-1; i > 0; i-- {
    if children.Item(i) == n.self {
      return children.Item(i-1)
    }
  }
  return Node(nil)
}
func (n *_node) NextSibling() Node {
  children := n.p.ChildNodes()
  for i := uint(0); i < children.Length()-1; i++ {
    if children.Item(i) == n.self {
      return children.Item(i+1)
    }
  }
  return Node(nil)
}
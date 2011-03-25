using System;
using System.Collections;
using System.Configuration;
using System.Data;
using System.Linq;
using System.Web;
using System.Web.Security;
using System.Web.UI;
using System.Web.UI.HtmlControls;
using System.Web.UI.WebControls;
using System.Web.UI.WebControls.WebParts;
using System.Xml.Linq;



namespace Lab9Pt2
{
    public partial class WebForm1 : System.Web.UI.Page
    {
        ws1.WebService1 ws = new ws1.WebService1();
        DataSet ds1;
        DateTime loadtime;


        void load_dataset(bool update)
        {
            
            if (update)
            {
                ds1 = ws.GetTasks();
                Session["dataset"] = ds1;

                loadtime = ws.GetServerTime();
                //Session["loadtime"] = loadtime;
                HiddenField1.Value = loadtime.ToString();
            }
            else
            {
                ds1 = (DataSet)Session["dataset"];
                //loadtime = (DateTime)Session["loadtime"];
                loadtime = Convert.ToDateTime(HiddenField1.Value);
            }
            bind();
            Label4.Text = "Last refreshed at: " + loadtime.ToString();
        }

        protected void bind()
        {
            GridView1.DataSource = ds1;
            GridView1.DataBind();
        }



        protected void Page_Load(object sender, EventArgs e)
        {
            response.Text = "";
            Label4.Text = "";
            load_dataset(!IsPostBack);
        }




        protected DataSet make_set(String name, String due, String owner)
        {
            DataSet task = new DataSet("new");
            DataTable tasktable = task.Tables.Add("task");
            DataColumn pkCol = tasktable.Columns.Add("name", typeof(String));
            DataColumn pkCol1 = tasktable.Columns.Add("due", typeof(DateTime));
            DataColumn pkCol2 = tasktable.Columns.Add("owner", typeof(String));
            DataColumn pkCol3 = tasktable.Columns.Add("modified", typeof(DateTime));
            DataRow dr = tasktable.NewRow();

            dr["name"] = name;
            try
            {
                dr["due"] = due;
            }
            catch (Exception ex)
            {
                response.Text = "invalid time format";
                return null;
            }
            dr["owner"] = owner;
            tasktable.Rows.Add(dr);
            return task;
        }

        protected DataSet make_set(String name, String due, String owner, DateTime modified)
        {

            DataSet task = make_set(name, due, owner);

            try
            {
                task.Tables["task"].Rows[0]["modified"] = modified;
            }
            catch (Exception ex) 
            {
                //task.Tables["task"].Rows[0]["modified"] = DateTime.Now; 
            }
            return task;
        }

        protected void Button1_Click(object sender, EventArgs e)
        {
            String name = tb_name.Text;
            String due = tb_due.Text;
            String owner = tb_owner.Text;
            String err = null;

            DataSet task = make_set(name, due, owner);
            if (task != null)
            {
                bool success = ws.AddTask(task, ref err);
                response.Text = (success) ? "done!" : err;
            }
            load_dataset(true);
            
        }

        // modify button
        protected void Button2_Click(object sender, EventArgs e)
        {
            String name = tb_name.Text;
            String due = tb_due.Text;
            String owner = tb_owner.Text;
            String err = null;
            DataSet task;
            task = make_set(name, due, owner, loadtime);

            if (task != null)
            {
                bool success = ws.ModifyTask(task, ref err);
                response.Text = "Reponse from server: " + ((err == null) ? "Done!" : ("<font color=\"red\">" + err + "</font>"));
            }
        }

        //refresh button
        protected void Button3_Click(object sender, EventArgs e)
        {
            load_dataset(true);
        }

        // remove button
        protected void Button4_Click(object sender, EventArgs e)
        {
            String name = tb_name.Text;
            String Error = "";
            bool ret;
            ret = ws.remove(name, ref Error);
            if (!ret)
                response.Text = "error: " + Error;

            load_dataset(true);
        }
    }
}
